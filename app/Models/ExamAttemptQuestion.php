<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttemptQuestion extends Model
{
    protected $fillable = [
        'exam_attempt_id',
        'question_id',
        'question_number',
        'category',
        'question_text',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'correct_answer',
        'selected_answer',
        'answered_at',
    ];

    protected $hidden = [
        'correct_answer',
    ];

    protected function casts(): array
    {
        return [
            'question_number' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
