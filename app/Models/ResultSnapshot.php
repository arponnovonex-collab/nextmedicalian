<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'exam_registration_id',
        'student_id',
        'obtained_marks',
        'percentage',
        'overall_merit',
        'zone_merit',
        'center_merit',
        'is_passed',
        'correct_count',
        'incorrect_count',
        'unanswered_count',
        'version',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'obtained_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'is_passed' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ExamRegistration::class, 'exam_registration_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }
}
