<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'center_id',
        'room_number',
        'floor',
        'capacity',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ExamRegistration::class, 'room_id');
    }
}
