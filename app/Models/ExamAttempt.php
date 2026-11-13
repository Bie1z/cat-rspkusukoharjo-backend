<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_assignment_id',
        'started_at',
        'submitted_at',
        'status',
        'correct_count',
        'wrong_count',
        'unanswered_count',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'unanswered_count' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ExamAssignment::class, 'exam_assignment_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(
            ExamAttemptQuestion::class,
            'exam_attempt_id'
        );
    }
}
