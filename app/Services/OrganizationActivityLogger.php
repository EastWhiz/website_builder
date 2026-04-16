<?php

namespace App\Services;

use App\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\Auth;

class OrganizationActivityLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(?int $organizationId, string $action, array $metadata = []): void
    {
        try {
            OrganizationActivityLog::create([
                'organization_id' => $organizationId,
                'actor_user_id' => Auth::id(),
                'action' => $action,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            // Logging should never break primary business flow.
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logMoveOrClone(
        ?int $organizationId,
        string $action,
        string $entityType,
        int|string|null $entityId,
        ?int $fromOrgId,
        ?int $toOrgId,
        array $metadata = []
    ): void {
        $this->log($organizationId, $action, array_merge([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'from_org_id' => $fromOrgId,
            'to_org_id' => $toOrgId,
        ], $metadata));
    }
}
