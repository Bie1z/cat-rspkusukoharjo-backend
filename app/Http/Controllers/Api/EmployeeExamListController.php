<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeExamListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
$user = $request->attributes->get('authUser');

abort_unless(
    $user && $user->role === 'pegawai',
    403,
    'Akses hanya untuk pegawai.'
);

// Periksa status terbaru langsung dari database.
$isActive = DB::table('users')
    ->where('id', $user->id)
    ->where('role', 'pegawai')
    ->where('is_active', true)
    ->exists();

abort_unless(
    $isActive,
    403,
    'Akun Anda sedang nonaktif. Silakan hubungi administrator CAT.'
);

        // Ambil hanya ujian milik pegawai yang login.
        $assignments = DB::table('exam_assignments as ea')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->leftJoin(
                'exam_attempts as at',
                'at.exam_assignment_id',
                '=',
                'ea.id'
            )
            ->leftJoin('users as u', 'u.id', '=', 'ea.user_id')
            ->leftJoin('units as un', 'un.id', '=', 'u.unit_id')
            ->where('ea.user_id', $user->id)
            ->where('e.status', 'published')
            ->select([
                'e.id',
                'e.name',
                'e.description',
                'e.starts_at',
                'e.ends_at',
                'e.aik_count',
                'e.hospital_count',
                'e.competency_count',
                'e.status',
                'at.id as attempt_id',
                'at.status as attempt_status',
                'un.name as unit_name',
            ])
            ->orderBy('e.starts_at')
            ->get();

        $now = now('Asia/Jakarta');

        $data = $assignments->map(function ($row) use ($now) {
            $start = Carbon::parse(
                $row->starts_at,
                'Asia/Jakarta'
            );

            $end = Carbon::parse(
                $row->ends_at,
                'Asia/Jakarta'
            );

            if ($row->attempt_status === 'submitted') {
                $availability = 'completed';
            } elseif ($row->attempt_status === 'timed_out') {
                $availability = 'expired';
            } elseif ($now->greaterThanOrEqualTo($end)) {
                $availability = 'expired';
            } elseif ($now->lessThan($start)) {
                $availability = 'upcoming';
            } elseif ($row->attempt_status === 'in_progress') {
                $availability = 'in_progress';
            } else {
                $availability = 'available';
            }

            return [
                'id' => $row->id,
                'name' => $row->name,
                'description' => $row->description,
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $end->toIso8601String(),
                'total_questions' =>
                    (int) $row->aik_count +
                    (int) $row->hospital_count +
                    (int) $row->competency_count,
                'composition' => [
                    'aik' => (int) $row->aik_count,
                    'hospital' => (int) $row->hospital_count,
                    'competency' => (int) $row->competency_count,
                ],
                'unit_name' => $row->unit_name,
                'status' => $row->status,
                'availability' => $availability,
                'attempt_id' => $row->attempt_id,
                'attempt_status' => $row->attempt_status,
            ];
        });

        return response()->json([
            'data' => $data,
            'server_time' => $now->toIso8601String(),
        ]);
    }
}
