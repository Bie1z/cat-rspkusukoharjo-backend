<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminExamResultController extends Controller
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
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'status' => [
                'nullable',
                Rule::in([
                    'not_started',
                    'in_progress',
                    'submitted',
                    'timed_out',
                ]),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DB::table('exam_assignments as ea')
            ->join('exams as e', 'e.id', '=', 'ea.exam_id')
            ->join('users as u', 'u.id', '=', 'ea.user_id')
            ->leftJoin('units as un', 'un.id', '=', 'u.unit_id')
            ->leftJoin(
                'exam_attempts as at',
                'at.exam_assignment_id',
                '=',
                'ea.id'
            )
            ->select([
                'ea.id as assignment_id',
                'e.id as exam_id',
                'e.name as exam_name',
                'e.starts_at',
                'e.ends_at',
                'e.status as exam_status',
                'u.id as user_id',
                'u.name as participant_name',
                'u.nip',
                'u.position',
                'un.id as unit_id',
                'un.name as unit_name',
                'at.id as attempt_id',
                'at.started_at',
                'at.submitted_at',
                'at.correct_count',
                'at.wrong_count',
                'at.unanswered_count',
                'at.score',
            ])
            ->selectRaw(
                "COALESCE(at.status, 'not_started') as attempt_status"
            );

        if (!empty($filters['exam_id'])) {
            $query->where('e.id', $filters['exam_id']);
        }

        if (!empty($filters['unit_id'])) {
            $query->where('u.unit_id', $filters['unit_id']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'not_started') {
                $query->whereNull('at.id');
            } else {
                $query->where(
                    'at.status',
                    $filters['status']
                );
            }
        }

        if (!empty($filters['search'])) {
            $keyword = $filters['search'];

            $query->where(function ($q) use ($keyword) {
                $q->where('u.name', 'like', "%{$keyword}%")
                    ->orWhere('u.nip', 'like', "%{$keyword}%");
            });
        }

        $results = $query
            ->orderByDesc('e.starts_at')
            ->orderBy('u.name')
            ->paginate(25);

        return response()->json([
            'message' => 'Data hasil ujian berhasil diambil.',
            'data' => $results->items(),
            'pagination' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
