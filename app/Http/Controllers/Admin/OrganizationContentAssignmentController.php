<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignContentToOrganizationRequest;
use App\Services\OrganizationActivityLogger;
use Illuminate\Support\Facades\DB;

class OrganizationContentAssignmentController extends Controller
{
    /** @var array<string, string> */
    private const TABLES = [
        'angle' => 'angles',
        'angle_template' => 'angle_templates',
        'thank_you_page' => 'thank_you_pages',
        'user_api_instance' => 'user_api_instances',
    ];

    public function __construct(private readonly OrganizationActivityLogger $activityLogger)
    {
    }

    /**
     * Step 4.2: Super Admin assigns existing content to an organization.
     * organization_id is always updated; user_id is reset only when needed.
     */
    public function assignToOrganization(AssignContentToOrganizationRequest $request)
    {
        $validated = $request->validated();
        $organizationId = (int) $validated['organization_id'];
        $items = $this->normalizeItems((array) ($validated['items'] ?? []));

        if ($items === []) {
            return sendResponse(false, 'No valid items to assign.', null, null, null, 422);
        }

        // May be 0 when the org has no usable primary user. We only require it
        // when an item's owner actually needs to be reset.
        $registeredOrgUserId = $this->resolveRegisteredOrganizationUserId($organizationId);

        $countsByType = [];
        $updatedItems = [];

        try {
            DB::transaction(function () use ($items, $organizationId, $registeredOrgUserId, &$countsByType, &$updatedItems) {
                foreach ($items as $item) {
                    $type = $item['type'];
                    $id = $item['id'];
                    $table = self::TABLES[$type];

                    $existing = DB::table($table)
                        ->where('id', $id)
                        ->whereNull('deleted_at')
                        ->first(['id', 'organization_id', 'user_id']);

                    if (!$existing) {
                        throw new \RuntimeException("Could not assign {$type} #{$id}. It may be missing or archived.");
                    }

                    $fromUserId = (int) ($existing->user_id ?? 0);
                    $shouldResetOwner = $fromUserId === 0
                        || $this->isSuperAdminUserId($fromUserId)
                        || !$this->isActiveMemberOfOrganization($fromUserId, $organizationId);
                    $resetOwnerUserId = $registeredOrgUserId;

                    $payload = [
                        'organization_id' => $organizationId,
                        'updated_at' => now(),
                    ];
                    if ($shouldResetOwner) {
                        if ($resetOwnerUserId < 1) {
                            throw new \RuntimeException('Selected organization does not have a valid registered user.');
                        }
                        $payload['user_id'] = $resetOwnerUserId;
                    }

                    $updated = DB::table($table)
                        ->where('id', $id)
                        ->update($payload);
                    if ($updated !== 1) {
                        throw new \RuntimeException("Could not assign {$type} #{$id}. It may be missing or archived.");
                    }

                    $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;
                    $updatedItems[] = [
                        'type' => $type,
                        'id' => $id,
                        'from_user_id' => $fromUserId,
                        'to_user_id' => $shouldResetOwner ? $resetOwnerUserId : $fromUserId,
                        'owner_reset' => $shouldResetOwner,
                    ];
                }
            });
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, null, null, 422);
        } catch (\Throwable $e) {
            return sendResponse(false, 'Assignment to organization failed.', null, null, null, 500);
        }

        $this->activityLogger->logMoveOrClone(
            $organizationId,
            'org.content.assign_to_org_pool',
            'organization_content_batch',
            null,
            null,
            $organizationId,
            [
                'counts_by_type' => $countsByType,
                'items' => $updatedItems,
            ]
        );

        return sendResponse(true, 'Content assigned to organization successfully.', [
            'organization_id' => $organizationId,
            'counts_by_type' => $countsByType,
            'items' => $updatedItems,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @return list<array{type: string, id: int}>
     */
    private function normalizeItems(array $rawItems): array
    {
        $seen = [];
        $out = [];

        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = isset($row['type']) ? (string) $row['type'] : '';
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id < 1 || !isset(self::TABLES[$type])) {
                continue;
            }

            $key = $type . ':' . $id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['type' => $type, 'id' => $id];
        }

        return $out;
    }

    private function isSuperAdminUserId(int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        return DB::table('users')
            ->where('id', $userId)
            ->where('role_id', 1)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function isActiveMemberOfOrganization(int $userId, int $organizationId): bool
    {
        if ($userId < 1 || $organizationId < 1) {
            return false;
        }

        return DB::table('organization_user')
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
    }

    private function resolveRegisteredOrganizationUserId(int $organizationId): int
    {
        if ($organizationId < 1) {
            return 0;
        }

        $ownerId = DB::table('organizations')
            ->where('id', $organizationId)
            ->value('primary_user_id');

        $ownerId = (int) ($ownerId ?? 0);
        if ($ownerId < 1) {
            return 0;
        }

        $isUsable = DB::table('users')
            ->where('id', $ownerId)
            ->whereNull('deleted_at')
            ->exists();

        return $isUsable ? $ownerId : 0;
    }

}
