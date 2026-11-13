<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeHistoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authUser');

        abort_unless(
            $user && $user->role === 'pegawai',
            403,
            'Akses hanya untuk pegawai.'
        );

        // Ambil riwayat ujian milik pegawai yang login.
        // Sesi yang masih dikerjakan tidak ditampilkan.
        $attempts = ExamAttempt::query()
            ->with([
                'assignment.exam',
                'assignment.user.unit',
            ])
            ->withCount('questions')
            ->whereHas('assignment', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereIn('status', [
                'submitted',
                'timed_out',
            ])
            ->orderByDesc('started_at')
            ->get()
            ->map(function (ExamAttempt $attempt) {
                $exam = $attempt->assignment?->exam;
                $employee = $attempt->assignment?->user;

                return [
                    'id' => $attempt->id,

                    'date' => (
                        $attempt->submitted_at
                        ?? $attempt->started_at
                    )?->toIso8601String(),

                    'name' => $exam?->name ?? '-',

                    'unit' => $employee?->unit?->name ?? '-',

                    'questionCount' => (int) $attempt->questions_count,

                    'score' => $attempt->score !== null
                        ? (float) $attempt->score
                        : null,

                    'status' => $attempt->status === 'submitted'
                        ? 'Selesai'
                        : 'Waktu Habis',

                    'correctCount' => $attempt->correct_count,

                    'wrongCount' => $attempt->wrong_count,

                    'unansweredCount' => $attempt->unanswered_count,
                ];
            });

        return response()->json([
            'data' => $attempts,
        ]);
    }
}
