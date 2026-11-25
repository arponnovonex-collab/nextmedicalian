<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(
        ?User $user,
        string $action,
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?int $examId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        // Strip sensitive fields
        if ($oldValues) {
            unset($oldValues['password'], $oldValues['remember_token'], $oldValues['token']);
        }
        if ($newValues) {
            unset($newValues['password'], $newValues['remember_token'], $newValues['token']);
        }

        return AuditLog::create([
            'user_id' => $user?->id,
            'role' => $user?->role,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'exam_id' => $examId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
