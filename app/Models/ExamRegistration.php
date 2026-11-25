<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'center_id',
        'room_id',
        'seat_number',
        'admit_card_token',
        'admit_card_downloaded_count',
        'first_admit_download_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'first_admit_download_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class, 'room_id');
    }

    public function marks(): HasOne
    {
        return $this->hasOne(Marks::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class);
    }

    public function admitCardDownloads(): HasMany
    {
        return $this->hasMany(AdmitCardDownload::class);
    }

    public function resultSnapshot(): HasOne
    {
        return $this->hasOne(ResultSnapshot::class);
    }
}
