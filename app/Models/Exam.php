<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = [
        'name',
        'description',
        'starts_at',
        'ends_at',
        'aik_count',
        'hospital_count',
        'competency_count',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'aik_count' => 'integer',
            'hospital_count' => 'integer',
            'competency_count' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
