<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamAssignmentController extends Controller
{
    private function ensureAdmin(Request $request): User
    {
        $admin = $request->attributes->get('authUser');

        abort_unless(
            $admin && $admin->role === 'admin',
            403,
            'Akses hanya untuk admin.'
        );

        return $admin;
    }

    // GET /api/admin/exams/{exam}/assignments
    public function index(
        Request $request,
        Exam $exam
    ): JsonResponse {
        $this->ensureAdmin($request);

        $assignments = DB::table('exam_assignments')
            ->join(
                'users',
                'exam_assignments.user_id',
                '=',
                'users.id'
            )
            ->leftJoin(
                'units',
                'users.unit_id',
                '=',
                'units.id'
            )
            ->where('exam_assignments.exam_id', $exam->id)
            ->select(
                'exam_assignments.id',
                'exam_assignments.user_id',
                'users.name',
                'users.nip',
                'users.position',
                'units.name as unit',
                'exam_assignments.assigned_at'
            )
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'data' => $assignments,
        ]);
    }

    // POST /api/admin/exams/{exam}/assignments
    public function store(
        Request $request,
        Exam $exam
    ): JsonResponse {
        $admin = $this->ensureAdmin($request);

        $validator = Validator::make($request->all(), [
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => [
                'required', 'integer', 'distinct',
                'exists:users,id',
            ],
            'unit_ids' => ['sometimes', 'array'],
            'unit_ids.*' => [
                'required', 'integer', 'distinct',
                'exists:units,id',
            ],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (
                empty($request->input('user_ids', [])) &&
                empty($request->input('unit_ids', []))
            ) {
                $validator->errors()->add(
                    'participants',
                    'Pilih minimal satu pegawai atau unit.'
                );
            }
        });

        $data = $validator->validate();

        $result = DB::transaction(function () use (
            $exam,
            $admin,
            $data
        ) {
            $lockedExam = Exam::query()
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $lockedExam->status === 'draft',
                422,
                'Peserta hanya dapat ditambahkan pada paket draf.'
            );

            $userIds = $data['user_ids'] ?? [];
            $unitIds = $data['unit_ids'] ?? [];

            $query = User::query()
                ->where('role', 'pegawai')
                ->where(function ($query) use (
                    $userIds,
                    $unitIds
                ) {
                    if (!empty($userIds)) {
                        $query->whereIn('id', $userIds);
                    }

                    if (!empty($unitIds)) {
                        if (!empty($userIds)) {
                            $query->orWhereIn('unit_id', $unitIds);
                        } else {
                            $query->whereIn('unit_id', $unitIds);
                        }
                    }
                });

            $ids = $query->pluck('id')->unique()->values();

            $now = now();

            $rows = $ids->map(fn ($id) => [
                'exam_id' => $lockedExam->id,
                'user_id' => $id,
                'assigned_by' => $admin->id,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            $added = empty($rows)
                ? 0
                : DB::table('exam_assignments')
                    ->insertOrIgnore($rows);

            $total = DB::table('exam_assignments')
                ->where('exam_id', $lockedExam->id)
                ->count();

            return [
                'selected' => $ids->count(),
                'added' => $added,
                'total' => $total,
            ];
        });

        return response()->json([
            'message' => 'Penugasan peserta berhasil diproses.',
            'data' => $result,
        ]);
    }
}
