<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeAttemptController extends Controller
{
    private function employee(Request $request)
    {
        $user = $request->attributes->get('authUser');

        abort_unless(
            $user && $user->role === 'pegawai',
            403,
            'Akses hanya untuk pegawai.'
        );

        return $user;
    }
private function lockedAttempt(int $id, int $userId): ExamAttempt
{
    // Periksa dan kunci data pegawai selama transaksi.
    $activeUser = DB::table('users')
        ->where('id', $userId)
        ->where('role', 'pegawai')
        ->lockForUpdate()
        ->first(['id', 'is_active']);

    abort_unless(
        $activeUser && (bool) $activeUser->is_active,
        403,
        'Akun Anda sedang nonaktif. Silakan hubungi administrator CAT.'
    );

    // Ambil sesi ujian.
    $attempt = ExamAttempt::query()
        ->with('assignment.exam')
        ->lockForUpdate()
        ->findOrFail($id);

    // Pastikan sesi merupakan milik pegawai yang login.
    abort_unless(
        $attempt->assignment &&
        (int) $attempt->assignment->user_id === $userId,
        403,
        'Sesi ujian bukan milik Anda.'
    );

    return $attempt;
}


    private function finalize(
        ExamAttempt $attempt,
        string $status
    ): void {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $questions = $attempt->questions()->get();

        $correct = $questions->filter(
            fn ($q) => $q->selected_answer !== null &&
                $q->selected_answer === $q->correct_answer
        )->count();

        $unanswered = $questions->filter(
            fn ($q) => $q->selected_answer === null
        )->count();

        $total = $questions->count();
        $wrong = $total - $correct - $unanswered;

        $attempt->update([
            'status' => $status,
            'submitted_at' => now(),
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'unanswered_count' => $unanswered,
            'score' => $total > 0
                ? round(($correct / $total) * 100, 2)
                : 0,
        ]);
    }
private function resultSummary(ExamAttempt $attempt): array
{
    $questions = $attempt->questions()->get();

    $correct = $questions->filter(
        fn ($q) =>
            $q->selected_answer !== null &&
            $q->selected_answer === $q->correct_answer
    )->count();

    $unanswered = $questions->filter(
        fn ($q) => $q->selected_answer === null
    )->count();

    $total = $questions->count();
    $wrong = $total - $correct - $unanswered;

    $categories = [];

    foreach ([
        'AIK',
        'Kerumahsakitan',
        'Kompetensi',
    ] as $category) {

        $items = $questions->where(
            'category',
            $category
        );

        $categoryTotal = $items->count();

        $categoryCorrect = $items->filter(
            fn ($q) =>
                $q->selected_answer !== null &&
                $q->selected_answer === $q->correct_answer
        )->count();

        $categoryUnanswered = $items->filter(
            fn ($q) => $q->selected_answer === null
        )->count();

        $categoryWrong =
            $categoryTotal -
            $categoryCorrect -
            $categoryUnanswered;

        $categories[$category] = [
            'correct' => $categoryCorrect,
            'wrong' => $categoryWrong,
            'unanswered' => $categoryUnanswered,
            'total' => $categoryTotal,

            'score' => $categoryTotal > 0
                ? round(
                    ($categoryCorrect / $categoryTotal) * 100,
                    2
                )
                : 0,
        ];
    }

    return [
        'total_questions' => $total,
        'correct_count' => $correct,
        'wrong_count' => $wrong,
        'unanswered_count' => $unanswered,

        'score' => $total > 0
            ? round(($correct / $total) * 100, 2)
            : 0,

        'categories' => $categories,
    ];
}
    private function expireIfNeeded(ExamAttempt $attempt): void
    {
        if (
            $attempt->status === 'in_progress' &&
            now()->greaterThanOrEqualTo(
                $attempt->assignment->exam->ends_at
            )
        ) {
            $this->finalize($attempt, 'timed_out');
        }
    }

    private function payload(ExamAttempt $attempt): array
{
    $attempt->loadMissing([
        'assignment.exam',
        'assignment.user.unit',
    ]);

    $exam = $attempt->assignment->exam;
    $employee = $attempt->assignment->user;

    $data = [
        'attempt_id' => $attempt->id,
        'exam_id' => $exam->id,
        'exam_name' => $exam->name,
        'status' => $attempt->status,
        'started_at' => $attempt->started_at,
        'submitted_at' => $attempt->submitted_at,
        'ends_at' => $exam->ends_at,
        'server_time' => now()->toIso8601String(),
        'total_questions' => $attempt->questions()->count(),
    ];

    // Jika ujian selesai, tampilkan hasil penilaian.
    if ($attempt->status !== 'in_progress') {

        return array_merge($data, [
            'employee' => [
                'name' => $employee?->name,
                'nip' => $employee?->nip,
                'position' => $employee?->position,
                'unit' => $employee?->unit?->name,
            ],

            ...$this->resultSummary($attempt),
        ]);
    }

    // Jika masih mengerjakan, tampilkan soal tanpa kunci jawaban.
    $data['questions'] = $attempt->questions()
        ->orderBy('question_number')
        ->get()
        ->map(fn ($q) => [
            'id' => $q->id,
            'number' => $q->question_number,
            'category' => $q->category,
            'question' => $q->question_text,

            'options' => [
                ['key' => 'A', 'text' => $q->option_a],
                ['key' => 'B', 'text' => $q->option_b],
                ['key' => 'C', 'text' => $q->option_c],
                ['key' => 'D', 'text' => $q->option_d],
            ],

            'selectedAnswer' => $q->selected_answer,
        ])
        ->values();

    return $data;
}


    // GET /api/employee/attempts/{attempt}
    public function show(
        Request $request,
        ExamAttempt $attempt
    ): JsonResponse {
        $user = $this->employee($request);

        return DB::transaction(function () use ($attempt, $user) {
            $attempt = $this->lockedAttempt(
                $attempt->id,
                $user->id
            );

            $this->expireIfNeeded($attempt);

            return response()->json([
                'data' => $this->payload($attempt),
            ]);
        });
    }

    // PUT /api/employee/attempts/{attempt}/answers/{questionId}
    public function saveAnswer(
        Request $request,
        ExamAttempt $attempt,
        int $questionId
    ): JsonResponse {
        $user = $this->employee($request);

        $data = $request->validate([
            'answer' => [
                'present',
                'nullable',
                'in:A,B,C,D',
            ],
        ]);

        return DB::transaction(function () use (
            $attempt,
            $user,
            $questionId,
            $data
        ) {
            $attempt = $this->lockedAttempt(
                $attempt->id,
                $user->id
            );

            $this->expireIfNeeded($attempt);

            if ($attempt->status !== 'in_progress') {
                return response()->json([
                    'message' => 'Ujian telah selesai. Jawaban dikunci.',
                ], 409);
            }

            $question = $attempt->questions()
                ->whereKey($questionId)
                ->firstOrFail();

            $question->update([
                'selected_answer' => $data['answer'],
                'answered_at' => now(),
            ]);

            return response()->json([
                'message' => 'Jawaban berhasil disimpan.',
                'data' => [
                    'question_id' => $question->id,
                    'selectedAnswer' => $question->selected_answer,
                    'saved_at' => now()->toIso8601String(),
                ],
            ]);
        });
    }

    // POST /api/employee/attempts/{attempt}/submit
public function submit(
    Request $request,
    ExamAttempt $attempt
): JsonResponse {
    $user = $this->employee($request);

    return DB::transaction(function () use (
        $attempt,
        $user
    ) {
        $attempt = $this->lockedAttempt(
            $attempt->id,
            $user->id
        );

        $this->expireIfNeeded($attempt);

        if ($attempt->status === 'in_progress') {
            $this->finalize($attempt, 'submitted');
        }

        return response()->json([
            'message' => 'Ujian berhasil diselesaikan.',
            'data' => $this->payload($attempt),
        ]);
    });
}
}
