<?php

namespace App\Console\Commands;

use App\Models\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseExpiredExams extends Command
{
    protected $signature = 'cat:close-expired';

    protected $description =
        'Menutup sesi CAT yang melewati batas akhir jadwal ujian.';

    public function handle(): int
    {
        $closed = 0;

        DB::table('exam_attempts')
            ->join(
                'exam_assignments',
                'exam_attempts.exam_assignment_id',
                '=',
                'exam_assignments.id'
            )
            ->join(
                'exams',
                'exam_assignments.exam_id',
                '=',
                'exams.id'
            )
            ->where('exam_attempts.status', 'in_progress')
            ->where('exams.ends_at', '<=', now())
            ->select('exam_attempts.id')
            ->chunkById(
                100,
                function ($rows) use (&$closed) {
                    foreach ($rows as $row) {
                        DB::transaction(function () use (
                            $row,
                            &$closed
                        ) {
                            $attempt = ExamAttempt::query()
                                ->lockForUpdate()
                                ->find($row->id);

                            if (
                                !$attempt ||
                                $attempt->status !== 'in_progress'
                            ) {
                                return;
                            }

                            $examEnd = DB::table('exam_assignments')
                                ->join(
                                    'exams',
                                    'exam_assignments.exam_id',
                                    '=',
                                    'exams.id'
                                )
                                ->where(
                                    'exam_assignments.id',
                                    $attempt->exam_assignment_id
                                )
                                ->value('exams.ends_at');

                            if (
                                !$examEnd ||
                                now()->lessThan($examEnd)
                            ) {
                                return;
                            }

                            $questions = $attempt->questions()
                                ->get();

                            $total = $questions->count();

                            $correct = $questions
                                ->filter(
                                    fn ($q) =>
                                        $q->selected_answer !== null &&
                                        $q->selected_answer ===
                                        $q->correct_answer
                                )
                                ->count();

                            $unanswered = $questions
                                ->filter(
                                    fn ($q) =>
                                        $q->selected_answer === null
                                )
                                ->count();

                            $wrong = $total -
                                $correct -
                                $unanswered;

                            $attempt->update([
                                'status' => 'timed_out',
                                'submitted_at' => now(),
                                'correct_count' => $correct,
                                'wrong_count' => $wrong,
                                'unanswered_count' => $unanswered,
                                'score' => $total > 0
                                    ? round(
                                        $correct / $total * 100,
                                        2
                                    )
                                    : 0,
                            ]);

                            $closed++;
                        });
                    }
                },
                'exam_attempts.id',
                'id'
            );

        $this->info(
            "Selesai. {$closed} sesi ujian ditutup otomatis."
        );

        return self::SUCCESS;
    }
}
