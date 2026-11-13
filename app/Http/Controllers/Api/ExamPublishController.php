<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamPublishController extends Controller
{
    public function __invoke(
        Request $request,
        Exam $exam
    ): JsonResponse {
        $admin = $request->attributes->get('authUser');

        abort_unless(
            $admin && $admin->role === 'admin',
            403,
            'Akses hanya untuk admin.'
        );

        return DB::transaction(function () use ($exam) {
            $exam = Exam::query()
                ->whereKey($exam->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($exam->status !== 'draft') {
                return response()->json([
                    'message' => 'Hanya paket draft yang dapat dipublikasikan.',
                ], 422);
            }

            if (!$exam->starts_at->isFuture()) {
                return response()->json([
                    'message' => 'Waktu mulai ujian harus di masa depan.',
                ], 422);
            }

            if (!$exam->ends_at->greaterThan($exam->starts_at)) {
                return response()->json([
                    'message' => 'Jadwal ujian tidak valid.',
                ], 422);
            }

            if (
                $exam->aik_count !== 10 ||
                $exam->hospital_count !== 10 ||
                $exam->competency_count !== 10
            ) {
                return response()->json([
                    'message' => 'Komposisi ujian wajib 10 AIK, 10 Kerumahsakitan, dan 10 Kompetensi.',
                ], 422);
            }

            $participants = DB::table('exam_assignments')
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
                    'users.id',
                    'users.role',
                    'users.unit_id',
                    'units.name as unit_name'
                )
                ->get();

            if ($participants->isEmpty()) {
                return response()->json([
                    'message' => 'Pilih minimal satu peserta sebelum publikasi.',
                ], 422);
            }

            if ($participants->contains(
                fn ($p) => $p->role !== 'pegawai' || $p->unit_id === null
            )) {
                return response()->json([
                    'message' => 'Semua peserta harus merupakan pegawai dan memiliki unit kerja.',
                ], 422);
            }

            $aik = Question::query()
                ->where('category', 'AIK')
                ->where('status', 'Aktif')
                ->whereNull('unit_id')
                ->count();

            $hospital = Question::query()
                ->where('category', 'Kerumahsakitan')
                ->where('status', 'Aktif')
                ->whereNull('unit_id')
                ->count();

            $shortages = [];

            if ($aik < 10) {
                $shortages[] = [
                    'category' => 'AIK',
                    'available' => $aik,
                    'required' => 10,
                ];
            }

            if ($hospital < 10) {
                $shortages[] = [
                    'category' => 'Kerumahsakitan',
                    'available' => $hospital,
                    'required' => 10,
                ];
            }

            $unitIds = $participants
                ->pluck('unit_id')
                ->unique()
                ->values();

            $competencyCounts = Question::query()
                ->where('category', 'Kompetensi')
                ->where('status', 'Aktif')
                ->whereIn('unit_id', $unitIds)
                ->selectRaw('unit_id, COUNT(*) as total')
                ->groupBy('unit_id')
                ->pluck('total', 'unit_id');

            $unitNames = $participants
                ->unique('unit_id')
                ->pluck('unit_name', 'unit_id');

            foreach ($unitIds as $unitId) {
                $available = (int) (
                    $competencyCounts->get($unitId, 0)
                );

                if ($available < 10) {
                    $shortages[] = [
                        'category' => 'Kompetensi',
                        'unit_id' => $unitId,
                        'unit' => $unitNames->get($unitId),
                        'available' => $available,
                        'required' => 10,
                    ];
                }
            }

            if (!empty($shortages)) {
                return response()->json([
                    'message' => 'Paket belum bisa dipublikasikan. Jumlah soal aktif belum mencukupi.',
                    'shortages' => $shortages,
                ], 422);
            }

            $exam->update([
                'status' => 'published',
            ]);

            return response()->json([
                'message' => 'Paket ujian berhasil dipublikasikan.',
                'data' => [
                    'id' => $exam->id,
                    'status' => $exam->status,
                    'participants' => $participants->count(),
                    'total_questions' => 30,
                ],
            ]);
        });
    }
}
