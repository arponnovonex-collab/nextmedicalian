<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarksCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'marks_id',
        'requested_by',
        'old_marks',
        'proposed_marks',
        'reason',
        'status',
        'reviewed_by',
        'admin_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_marks' => 'decimal:2',
            'proposed_marks' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function marks(): BelongsTo
    {
        return $this->belongsTo(Marks::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
