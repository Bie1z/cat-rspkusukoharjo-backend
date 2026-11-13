<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        $user = $request->attributes->get('authUser');

        abort_unless(
            $user && $user->role === 'admin',
            403,
            'Akses hanya untuk admin.'
        );
    }

    // GET /api/admin/exams
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $exams = Exam::query()
            ->withCount('assignments')
            ->orderByDesc('starts_at')
            ->get();

        return response()->json([
            'data' => $exams,
        ]);
    }

    // POST /api/admin/exams
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => [
                'required',
                'date_format:Y-m-d H:i',
            ],
            'ends_at' => [
                'required',
                'date_format:Y-m-d H:i',
            ],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::createFromFormat(
                '!Y-m-d H:i',
                $request->input('starts_at'),
                'Asia/Jakarta'
            );

            $end = Carbon::createFromFormat(
                '!Y-m-d H:i',
                $request->input('ends_at'),
                'Asia/Jakarta'
            );

            if (!$end->greaterThan($start)) {
                $validator->errors()->add(
                    'ends_at',
                    'Waktu selesai harus setelah waktu mulai.'
                );
                return;
            }

            if ($start->diffInMinutes($end) != 60) {
                $validator->errors()->add(
                    'ends_at',
                    'Durasi ujian harus tepat 60 menit.'
                );
            }

            if (!$start->isFuture()) {
                $validator->errors()->add(
                    'starts_at',
                    'Jadwal mulai harus di masa depan.'
                );
            }
        });

        $data = $validator->validate();

        $admin = $request->attributes->get('authUser');

        $exam = Exam::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'aik_count' => 10,
            'hospital_count' => 10,
            'competency_count' => 10,
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        return response()->json([
            'message' => 'Paket ujian berhasil dibuat.',
            'data' => $exam,
        ], 201);
    }

    // GET /api/admin/exams/{exam}
    public function show(Request $request, Exam $exam): JsonResponse
    {
        $this->ensureAdmin($request);

        $exam->loadCount('assignments');

        return response()->json([
            'data' => $exam,
        ]);
    }

    // GET /api/admin/exams/{exam}/delete-preview
    public function deletePreview(
        Request $request,
        Exam $exam
    ): JsonResponse {
        $this->ensureAdmin($request);

        $preview = $this->makeDeletePreview([
            (int) $exam->id,
        ]);

        return response()->json([
            'data' => $preview['exams'][0] ?? null,
        ]);
    }

    // POST /api/admin/exams/bulk-delete-preview
    public function bulkDeletePreview(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        $examIds = $this->validatedExamIds($request);

        return response()->json([
            'data' => $this->makeDeletePreview($examIds),
        ]);
    }

    // DELETE /api/admin/exams/{exam}
    public function destroy(
        Request $request,
        Exam $exam
    ): JsonResponse {
        $this->ensureAdmin($request);

        Validator::make($request->all(), [
            'confirmed' => ['required', 'accepted'],
        ])->validate();

        $result = $this->deleteExamRecords([
            (int) $exam->id,
        ]);

        return response()->json([
            'message' => 'Paket ujian dan seluruh data terkait berhasil dihapus.',
            'deleted' => $result['deleted'],
            'impact' => $result['impact'],
        ]);
    }

    // POST /api/admin/exams/bulk-delete
    public function bulkDestroy(
        Request $request
    ): JsonResponse {
        $this->ensureAdmin($request);

        $examIds = $this->validatedExamIds($request);

        Validator::make($request->all(), [
            'confirmed' => ['required', 'accepted'],
        ])->validate();

        $result = $this->deleteExamRecords($examIds);

        return response()->json([
            'message' => 'Seluruh paket ujian terpilih berhasil dihapus.',
            'deleted' => $result['deleted'],
            'impact' => $result['impact'],
            'deleted_exam_ids' => $result['deleted_exam_ids'],
        ]);
    }

    /**
     * Validasi ID paket untuk penghapusan massal.
     */
    private function validatedExamIds(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'exam_ids' => [
                'required',
                'array',
                'min:1',
                'max:500',
            ],
            'exam_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:exams,id',
            ],
        ])->validate();

        return array_map(
            'intval',
            $data['exam_ids']
        );
    }

    /**
     * Menghitung dampak penghapusan tanpa mengubah database.
     */
    private function makeDeletePreview(array $examIds): array
    {
        $exams = DB::table('exams')
            ->whereIn('id', $examIds)
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'status',
            ]);

        $assignmentCounts = DB::table('exam_assignments')
            ->select('exam_id')
            ->selectRaw('COUNT(*) AS assignments_count')
            ->whereIn('exam_id', $examIds)
            ->groupBy('exam_id')
            ->get()
            ->keyBy('exam_id');

        $attemptCounts = DB::table('exam_attempts AS attempts')
            ->join(
                'exam_assignments AS assignments',
                'assignments.id',
                '=',
                'attempts.exam_assignment_id'
            )
            ->whereIn('assignments.exam_id', $examIds)
            ->select('assignments.exam_id')
            ->selectRaw('COUNT(attempts.id) AS attempts_count')
            ->selectRaw("
                SUM(
                    CASE WHEN attempts.score IS NOT NULL
                    THEN 1 ELSE 0 END
                ) AS scored_attempts_count
            ")
            ->selectRaw("
                SUM(
                    CASE WHEN attempts.status = 'in_progress'
                    THEN 1 ELSE 0 END
                ) AS in_progress_attempts_count
            ")
            ->selectRaw("
                SUM(
                    CASE WHEN attempts.status IN ('submitted', 'timed_out')
                    THEN 1 ELSE 0 END
                ) AS finished_attempts_count
            ")
            ->groupBy('assignments.exam_id')
            ->get()
            ->keyBy('exam_id');

        $snapshotCounts = DB::table(
            'exam_attempt_questions AS snapshots'
        )
            ->join(
                'exam_attempts AS attempts',
                'attempts.id',
                '=',
                'snapshots.exam_attempt_id'
            )
            ->join(
                'exam_assignments AS assignments',
                'assignments.id',
                '=',
                'attempts.exam_assignment_id'
            )
            ->whereIn('assignments.exam_id', $examIds)
            ->select('assignments.exam_id')
            ->selectRaw('COUNT(snapshots.id) AS snapshots_count')
            ->groupBy('assignments.exam_id')
            ->get()
            ->keyBy('exam_id');

        $rows = [];

        foreach ($exams as $exam) {
            $assignment = $assignmentCounts->get($exam->id);
            $attempt = $attemptCounts->get($exam->id);
            $snapshot = $snapshotCounts->get($exam->id);

            $assignmentCount = (int) (
                $assignment->assignments_count ?? 0
            );

            $attemptCount = (int) (
                $attempt->attempts_count ?? 0
            );

            $scoreCount = (int) (
                $attempt->scored_attempts_count ?? 0
            );

            $snapshotCount = (int) (
                $snapshot->snapshots_count ?? 0
            );

            $rows[] = [
                'id' => (int) $exam->id,
                'name' => $exam->name,
                'status' => $exam->status,
                'assignments_count' => $assignmentCount,
                'attempts_count' => $attemptCount,
                'scored_attempts_count' => $scoreCount,
                'in_progress_attempts_count' => (int) (
                    $attempt->in_progress_attempts_count ?? 0
                ),
                'finished_attempts_count' => (int) (
                    $attempt->finished_attempts_count ?? 0
                ),
                'snapshots_count' => $snapshotCount,
                'has_scores' => $scoreCount > 0,
                'warning_level' => $scoreCount > 0
                    ? 'high'
                    : ($attemptCount > 0 ? 'medium' : 'normal'),
            ];
        }

        return [
            'exams' => $rows,
            'totals' => [
                'exams_count' => count($rows),
                'assignments_count' => array_sum(
                    array_column($rows, 'assignments_count')
                ),
                'attempts_count' => array_sum(
                    array_column($rows, 'attempts_count')
                ),
                'scored_attempts_count' => array_sum(
                    array_column($rows, 'scored_attempts_count')
                ),
                'in_progress_attempts_count' => array_sum(
                    array_column($rows, 'in_progress_attempts_count')
                ),
                'finished_attempts_count' => array_sum(
                    array_column($rows, 'finished_attempts_count')
                ),
                'snapshots_count' => array_sum(
                    array_column($rows, 'snapshots_count')
                ),
            ],
        ];
    }

    /**
     * Menghapus paket dan seluruh data turunannya
     * secara atomik dalam satu transaksi.
     */
    private function deleteExamRecords(array $examIds): array
    {
        $examIds = array_values(array_unique(
            array_map('intval', $examIds)
        ));

        return DB::transaction(function () use ($examIds) {

            // Kunci paket ujian terlebih dahulu.
            $exams = Exam::query()
                ->whereIn('id', $examIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'name', 'status']);

            if ($exams->count() !== count($examIds)) {
                abort(
                    404,
                    'Satu atau lebih paket ujian tidak ditemukan.'
                );
            }

            $lockedExamIds = $exams
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Kunci seluruh penugasan terkait.
            $assignmentIds = DB::table('exam_assignments')
                ->whereIn('exam_id', $lockedExamIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Kunci seluruh attempt terkait.
            $attemptIds = [];

            if (!empty($assignmentIds)) {
                $attemptIds = DB::table('exam_attempts')
                    ->whereIn(
                        'exam_assignment_id',
                        $assignmentIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            // Hitung dampak aktual setelah data dikunci.
            $preview = $this->makeDeletePreview($lockedExamIds);

            // 1. Hapus snapshot soal dan jawaban.
            $deletedSnapshots = 0;

            if (!empty($attemptIds)) {
                $deletedSnapshots = DB::table(
                    'exam_attempt_questions'
                )
                    ->whereIn('exam_attempt_id', $attemptIds)
                    ->delete();
            }

            // 2. Hapus attempt beserta nilai.
            $deletedAttempts = 0;

            if (!empty($attemptIds)) {
                $deletedAttempts = DB::table('exam_attempts')
                    ->whereIn('id', $attemptIds)
                    ->delete();
            }

            // 3. Hapus penugasan peserta.
            $deletedAssignments = 0;

            if (!empty($assignmentIds)) {
                $deletedAssignments = DB::table('exam_assignments')
                    ->whereIn('id', $assignmentIds)
                    ->delete();
            }

            // 4. Hapus paket ujian.
            $deletedExams = DB::table('exams')
                ->whereIn('id', $lockedExamIds)
                ->delete();

            // Pastikan tidak ada penghapusan parsial.
            if (
                $deletedSnapshots !== $preview['totals']['snapshots_count']
                || $deletedAttempts !== $preview['totals']['attempts_count']
                || $deletedAssignments !== $preview['totals']['assignments_count']
                || $deletedExams !== count($lockedExamIds)
            ) {
                abort(
                    409,
                    'Jumlah data berubah saat penghapusan. Seluruh transaksi dibatalkan.'
                );
            }

            return [
                'deleted_exam_ids' => $lockedExamIds,
                'impact' => $preview['totals'],
                'deleted' => [
                    'exams_count' => $deletedExams,
                    'assignments_count' => $deletedAssignments,
                    'attempts_count' => $deletedAttempts,
                    'snapshots_count' => $deletedSnapshots,
                ],
            ];

        }, 3);
    }
}
