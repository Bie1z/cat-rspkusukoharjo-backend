<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authUser');

        // Ringkasan dashboard hanya untuk administrator.
        abort_unless(
            $user && $user->role === 'admin',
            403,
            'Akses hanya untuk Admin CAT.'
        );

        $now = now('Asia/Jakarta');

        // Hitung rata-rata nilai ujian yang sudah difinalisasi.
        // Ujian submitted dan timed_out mengikuti
        // aturan perhitungan monitoring CAT.
        $averageScore = ExamAttempt::query()
            ->whereIn('status', ['submitted', 'timed_out'])
            ->whereNotNull('score')
            ->avg('score');

        return response()->json([
            'data' => [

                // Total akun pegawai.
                'total_participants' => User::where(
                    'role',
                    'pegawai'
                )->count(),

                // Total unit kerja aktif.
                'active_units' => Unit::where(
                    'status',
                    'active'
                )->count(),

                // Total akun administrator.
                'admin_accounts' => User::where(
                    'role',
                    'admin'
                )->count(),

                // Total seluruh soal dalam bank soal.
                'total_questions' => Question::count(),

                // Paket ujian yang sedang berlangsung.
                'active_exams' => Exam::query()
                    ->where('status', 'published')
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>', $now)
                    ->count(),

                // Rata-rata nilai ujian yang telah difinalisasi.
                'average_score' => $averageScore === null
                    ? null
                    : round((float) $averageScore, 2),
            ],
        ]);
    }
}
