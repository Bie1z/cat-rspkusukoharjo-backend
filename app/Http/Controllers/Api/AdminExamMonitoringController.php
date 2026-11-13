<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminExamMonitoringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('authUser');

        abort_unless(
            $admin && $admin->role === 'admin',
            403,
            'Akses hanya untuk Admin CAT.'
        );

        $filters = $request->validate([
            'exam_id' => [
                'nullable',
                'integer',
                'exists:exams,id',
            ],
        ]);

        $query = DB::table('exam_assignments as ea')
            ->join(
                'exams as e',
                'e.id',
                '=',
                'ea.exam_id'
            )
            ->leftJoin(
                'exam_attempts as at',
                'at.exam_assignment_id',
                '=',
                'ea.id'
            );

        if (!empty($filters['exam_id'])) {
            $query->where(
                'e.id',
                $filters['exam_id']
            );
        }

        // Jumlah dihitung berdasarkan penugasan,
        // bukan jumlah seluruh akun pegawai.
        $summary = $query->selectRaw("
            COUNT(ea.id) AS total_assigned,

            SUM(
                CASE WHEN at.id IS NULL
                THEN 1 ELSE 0 END
            ) AS not_started,

            SUM(
                CASE WHEN at.status = 'in_progress'
                THEN 1 ELSE 0 END
            ) AS in_progress,

            SUM(
                CASE WHEN at.status = 'submitted'
                THEN 1 ELSE 0 END
            ) AS submitted,

            SUM(
                CASE WHEN at.status = 'timed_out'
                THEN 1 ELSE 0 END
            ) AS timed_out,

            AVG(
                CASE
                    WHEN at.status IN (
                        'submitted', 'timed_out'
                    )
                    THEN at.score
                    ELSE NULL
                END
            ) AS average_score
        ")->first();

        $submitted = (int) ($summary->submitted ?? 0);
        $timedOut = (int) ($summary->timed_out ?? 0);
        $total = (int) ($summary->total_assigned ?? 0);

        return response()->json([
            'message' => 'Ringkasan monitoring berhasil diambil.',
            'data' => [
                'total_assigned' => $total,
                'not_started' =>
                    (int) ($summary->not_started ?? 0),
                'in_progress' =>
                    (int) ($summary->in_progress ?? 0),
                'submitted' => $submitted,
                'timed_out' => $timedOut,
                'completed' => $submitted + $timedOut,
                'average_score' =>
                    $summary->average_score === null
                        ? null
                        : round(
                            (float) $summary->average_score,
                            2
                        ),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
