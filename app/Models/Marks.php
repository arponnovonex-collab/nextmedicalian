<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marks extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_registration_id',
        'evaluated_by',
        'evaluation_mode',
        'correct_answers',
        'incorrect_answers',
        'unanswered_questions',
        'positive_marks',
        'negative_marks',
        'unanswered_marks',
        'final_marks',
        'is_locked',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'positive_marks' => 'decimal:2',
            'negative_marks' => 'decimal:2',
            'unanswered_marks' => 'decimal:2',
            'final_marks' => 'decimal:2',
            'is_locked' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(MarksCorrectionRequest::class);
    }
}
