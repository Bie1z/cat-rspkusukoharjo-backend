<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeResultController extends Controller
{
    /**
     * Pastikan hasil ujian merupakan milik pegawai
     * yang sedang login dan sesi sudah selesai.
     */
    private function completedAttempt(
        Request $request,
        ExamAttempt $attempt
    ): ExamAttempt {
        $user = $request->attributes->get('authUser');

        abort_unless(
            $user && $user->role === 'pegawai',
            403,
            'Akses hanya untuk pegawai.'
        );

        $attempt->load([
            'assignment.exam',
            'assignment.user.unit',
            'questions',
        ]);

        abort_unless(
            $attempt->assignment &&
            (int) $attempt->assignment->user_id === (int) $user->id,
            403,
            'Hasil ujian bukan milik Anda.'
        );

        abort_unless(
            in_array($attempt->status, [
                'submitted',
                'timed_out',
            ], true),
            409,
            'Hasil ujian belum tersedia.'
        );

        abort_unless(
            $attempt->assignment->exam &&
            $attempt->assignment->user,
            404,
            'Data ujian atau pegawai tidak ditemukan.'
        );

        return $attempt;
    }

    /**
     * Menyiapkan data hasil ujian.
     */
    private function resultData(ExamAttempt $attempt): array
    {
        $employee = $attempt->assignment->user;
        $exam = $attempt->assignment->exam;
        $questions = $attempt->questions;

        $total = $questions->count();

        $correct = $questions->filter(
            fn ($q) =>
                $q->selected_answer !== null &&
                $q->selected_answer === $q->correct_answer
        )->count();

        $unanswered = $questions->whereNull(
            'selected_answer'
        )->count();

        $wrong = $total - $correct - $unanswered;

        $score = $attempt->score !== null
            ? (float) $attempt->score
            : ($total > 0
                ? round(($correct / $total) * 100, 2)
                : 0);

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

            $categoryUnanswered = $items->whereNull(
                'selected_answer'
            )->count();

            $categoryWrong =
                $categoryTotal -
                $categoryCorrect -
                $categoryUnanswered;

            $categories[] = [
                'name' => $category,
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

        $completedAt = $attempt->submitted_at
            ?? $attempt->started_at;

        $year = $completedAt
            ? $completedAt->format('Y')
            : now()->format('Y');

        return [
            'attempt_id' => $attempt->id,

            'certificate_number' =>
                'CAT-RSPKU-' . $year . '-' .
                str_pad(
                    (string) $attempt->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

            'exam' => [
                'name' => $exam->name,
            ],

            'employee' => [
                'name' => $employee->name,
                'nip' => $employee->nip,
                'position' => $employee->position ?? '-',
                'unit' => $employee->unit?->name ?? '-',
            ],

            'date' => $completedAt?->format('d/m/Y H:i'),

            'status' => $attempt->status,

            'status_label' => $attempt->status === 'submitted'
                ? 'Selesai'
                : 'Waktu Habis',

            'total' => $total,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'score' => $score,

            'categories' => $categories,
        ];
    }

    /**
     * GET /api/employee/attempts/{attempt}/result
     */
    public function show(
        Request $request,
        ExamAttempt $attempt
    ): JsonResponse {
        $attempt = $this->completedAttempt(
            $request,
            $attempt
        );

        return response()->json([
            'message' => 'Hasil ujian berhasil diambil.',
            'data' => $this->resultData($attempt),
        ]);
    }

    /**
     * GET /api/employee/attempts/{attempt}/certificate
     */
    public function certificate(
        Request $request,
        ExamAttempt $attempt
    ) {
        $attempt = $this->completedAttempt(
            $request,
            $attempt
        );

        $result = $this->resultData($attempt);

        $pdf = Pdf::loadView(
            'pdf.employee-certificate',
            compact('result')
        )->setPaper('a4', 'portrait');

        $filename = 'Sertifikat-CAT-' .
            $attempt->id . '.pdf';

        return $pdf->download($filename);
    }
}
