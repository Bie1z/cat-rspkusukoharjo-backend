<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeExamController extends Controller
{
    public function start(
        Request $request,
        Exam $exam
    ): JsonResponse {
        $authUser = $request->attributes->get('authUser');

        abort_unless(
            $authUser && $authUser->role === 'pegawai',
            403,
            'Hanya pegawai yang dapat mengikuti ujian.'
        );

        // Ambil status terbaru langsung dari database.
        $user = User::query()->find($authUser->id);

        abort_unless(
            $user && $user->role === 'pegawai',
            403,
            'Akun pegawai tidak ditemukan.'
        );

        // Pegawai nonaktif tidak boleh memulai
        // maupun melanjutkan sesi ujian.
        abort_unless(
            (bool) $user->is_active,
            403,
            'Akun Anda sedang nonaktif. Silakan hubungi administrator CAT.'
        );

        return DB::transaction(function () use ($exam, $user) {

            // Kunci penugasan agar dua klik bersamaan
            // tidak membuat dua sesi ujian.
            $assignment = ExamAssignment::query()
                ->where('exam_id', $exam->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $assignment,
                403,
                'Anda tidak terdaftar sebagai peserta ujian.'
            );

            $exam->refresh();

            // Periksa sesi sebelumnya.
            $attempt = ExamAttempt::query()
                ->where('exam_assignment_id', $assignment->id)
                ->first();

            if ($attempt) {
                abort_if(
                    $attempt->status !== 'in_progress',
                    409,
                    'Kesempatan ujian sudah digunakan.'
                );

                abort_if(
                    now()->greaterThanOrEqualTo($exam->ends_at),
                    409,
                    'Waktu ujian telah berakhir.'
                );

                return $this->attemptResponse($attempt, $exam);
            }

            abort_unless(
                $exam->status === 'published',
                422,
                'Paket ujian belum dipublikasikan.'
            );

            $now = now();

            abort_if(
                $now->lessThan($exam->starts_at),
                422,
                'Jadwal ujian belum dimulai.'
            );

            abort_if(
                $now->greaterThanOrEqualTo($exam->ends_at),
                422,
                'Jadwal ujian sudah berakhir.'
            );

            abort_unless(
                $user->unit_id,
                422,
                'Akun pegawai belum memiliki unit kerja.'
            );

            /*
             * PENGACAKAN SOAL BERIMBANG
             *
             * AIK:
             * 4 Mudah, 3 Sedang, 3 Sulit.
             *
             * Kerumahsakitan:
             * 3 Mudah, 4 Sedang, 3 Sulit.
             *
             * Kompetensi:
             * 3 Mudah, 3 Sedang, 4 Sulit.
             */

            $aik = $this->selectBalancedQuestions(
                'AIK',
                null,
                [
                    'Mudah' => 4,
                    'Sedang' => 3,
                    'Sulit' => 3,
                ]
            );

            $hospital = $this->selectBalancedQuestions(
                'Kerumahsakitan',
                null,
                [
                    'Mudah' => 3,
                    'Sedang' => 4,
                    'Sulit' => 3,
                ]
            );

            $competency = $this->selectBalancedQuestions(
                'Kompetensi',
                $user->unit_id,
                [
                    'Mudah' => 3,
                    'Sedang' => 3,
                    'Sulit' => 4,
                ]
            );

            // Pertahankan urutan kategori.
            // Tidak ada pengacakan global.
            $questions = $aik
                ->concat($hospital)
                ->concat($competency)
                ->values();

            abort_unless(
                $questions->count() === 30,
                422,
                'Jumlah soal ujian tidak sesuai dengan ketentuan.'
            );

            // Buat satu sesi ujian.
            $attempt = ExamAttempt::create([
                'exam_assignment_id' => $assignment->id,
                'started_at' => $now,
                'status' => 'in_progress',
            ]);

            // Simpan snapshot soal ke database.
            foreach ($questions as $index => $question) {
                $attempt->questions()->create([
                    'question_id' => $question->id,
                    'question_number' => $index + 1,
                    'category' => $question->category,
                    'question_text' => $question->question,
                    'option_a' => $question->option_a,
                    'option_b' => $question->option_b,
                    'option_c' => $question->option_c,
                    'option_d' => $question->option_d,
                    'correct_answer' => $question->correct_answer,
                ]);
            }

            return $this->attemptResponse($attempt, $exam);
        });
    }

    /**
     * Mengambil soal secara acak berdasarkan kategori,
     * unit kerja, dan tingkat kesulitan.
     */
    private function selectBalancedQuestions(
        string $category,
        ?int $unitId,
        array $quotas
    ) {
        $selected = collect();

        foreach ($quotas as $difficulty => $quantity) {

            $query = Question::query()
                ->where('category', $category)
                ->where('status', 'Aktif')
                ->where('difficulty', $difficulty);

            // AIK dan Kerumahsakitan bersifat umum.
            // Kompetensi mengikuti unit pegawai.
            if ($unitId === null) {
                $query->whereNull('unit_id');
            } else {
                $query->where('unit_id', $unitId);
            }

            $batch = $query
                ->inRandomOrder()
                ->limit($quantity)
                ->get();

            // Jangan lanjut jika kuota kesulitan tidak terpenuhi.
            abort_if(
                $batch->count() !== $quantity,
                422,
                "Bank soal {$category} tingkat {$difficulty} belum mencukupi. Dibutuhkan {$quantity} soal."
            );

            $selected = $selected->concat($batch);
        }

        // Acak urutan soal di dalam kategori.
        return $selected->shuffle()->values();
    }

    private function attemptResponse(
        ExamAttempt $attempt,
        Exam $exam
    ): JsonResponse {
        $questions = $attempt->questions()
            ->orderBy('question_number')
            ->get()
            ->map(fn ($question) => [
                'id' => $question->id,
                'number' => $question->question_number,
                'category' => $question->category,
                'question' => $question->question_text,
                'options' => [
                    [
                        'key' => 'A',
                        'text' => $question->option_a,
                    ],
                    [
                        'key' => 'B',
                        'text' => $question->option_b,
                    ],
                    [
                        'key' => 'C',
                        'text' => $question->option_c,
                    ],
                    [
                        'key' => 'D',
                        'text' => $question->option_d,
                    ],
                ],
                'selectedAnswer' => $question->selected_answer,
            ]);

        return response()->json([
            'message' => 'Sesi ujian berhasil dibuka.',
            'data' => [
                'attempt_id' => $attempt->id,
                'exam_id' => $exam->id,
                'exam_name' => $exam->name,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at,
                'ends_at' => $exam->ends_at,
                'server_time' => now()->toIso8601String(),
                'total_questions' => 30,
                'questions' => $questions,
            ],
        ]);
    }
}
