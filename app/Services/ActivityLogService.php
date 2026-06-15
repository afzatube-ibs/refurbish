<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogService
{
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $metadata = null
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
    }
}
