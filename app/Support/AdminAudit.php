<?php

namespace App\Support;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;

class AdminAudit
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        string|int|null $entityId = null,
        ?string $summary = null,
        ?array $meta = null,
        ?Request $request = null,
    ): void {
        $request ??= request();
        $actor = AdminAccess::resolveActor($request);
        if ($actor === null) {
            return;
        }

        AdminAuditLog::query()->create([
            'actor_type' => (string) ($actor['type'] ?? 'token'),
            'actor_name' => (string) ($actor['name'] ?? ''),
            'actor_role' => isset($actor['role']) ? (string) $actor['role'] : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'summary' => $summary !== null ? mb_substr($summary, 0, 500) : null,
            'meta' => $meta,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
