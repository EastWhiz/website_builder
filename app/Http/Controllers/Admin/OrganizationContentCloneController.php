<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloneOrganizationContentRequest;
use App\Services\OrganizationActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationContentCloneController extends Controller
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
     * Step 4.4: Super Admin cross-org clone API path (non-member clone path).
     */
    public function cloneCrossOrg(CloneOrganizationContentRequest $request)
    {
        $validated = $request->validated();
        $sourceOrgId = (int) $validated['source_organization_id'];
        $targetOrgId = (int) $validated['target_organization_id'];
        $targetUserId = (int) $validated['target_user_id'];
        $items = $this->normalizeItems((array) ($validated['items'] ?? []));

        if ($items === []) {
            return sendResponse(false, 'No valid items to clone.', null, null, null, 422);
        }

        $targetMembershipExists = DB::table('organization_user')
            ->where('organization_id', $targetOrgId)
            ->where('user_id', $targetUserId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();
        if (!$targetMembershipExists) {
            return sendResponse(false, 'Target user must be an active member of target organization.', null, null, null, 422);
        }

        $countsByType = [];
        $clonedItems = [];

        try {
            DB::transaction(function () use (
                $items,
                $sourceOrgId,
                $targetOrgId,
                $targetUserId,
                &$countsByType,
                &$clonedItems
            ) {
                foreach ($items as $item) {
                    [$newId, $sourceRow, $newRow] = $this->cloneMainRow(
                        $item['type'],
                        $item['id'],
                        $sourceOrgId,
                        $targetOrgId,
                        $targetUserId
                    );

                    if ($item['type'] === 'angle') {
                        $this->cloneAngleChildren((string) ($sourceRow['uuid'] ?? ''), (string) ($newRow['uuid'] ?? ''));
                    } elseif ($item['type'] === 'angle_template') {
                        $this->cloneAngleTemplateChildren((string) ($sourceRow['uuid'] ?? ''), (string) ($newRow['uuid'] ?? ''));
                    } elseif ($item['type'] === 'user_api_instance') {
                        $this->cloneUserApiInstanceValues((int) $item['id'], $newId);
                    }

                    $countsByType[$item['type']] = ($countsByType[$item['type']] ?? 0) + 1;
                    $clonedItems[] = [
                        'type' => $item['type'],
                        'source_id' => $item['id'],
                        'target_id' => $newId,
                    ];
                }
            });
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, null, null, 422);
        } catch (\Throwable $e) {
            return sendResponse(false, 'Cross-organization clone failed.', null, null, null, 500);
        }

        $this->activityLogger->logMoveOrClone(
            $targetOrgId,
            'org.content.clone_cross_org',
            'organization_content_batch',
            null,
            $sourceOrgId,
            $targetOrgId,
            [
                'target_user_id' => $targetUserId,
                'counts_by_type' => $countsByType,
                'items' => $clonedItems,
            ]
        );

        return sendResponse(true, 'Content cloned successfully.', [
            'from_org_id' => $sourceOrgId,
            'to_org_id' => $targetOrgId,
            'target_user_id' => $targetUserId,
            'counts_by_type' => $countsByType,
            'items' => $clonedItems,
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

    /**
     * @return array{0:int,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function cloneMainRow(
        string $type,
        int $sourceId,
        int $sourceOrgId,
        int $targetOrgId,
        int $targetUserId
    ): array {
        $table = self::TABLES[$type];
        $source = DB::table($table)
            ->where('id', $sourceId)
            ->where('organization_id', $sourceOrgId)
            ->whereNull('deleted_at')
            ->first();

        if (!$source) {
            throw new \RuntimeException("{$type} #{$sourceId} not found in source organization.");
        }

        $sourceRow = (array) $source;
        $newRow = $sourceRow;
        unset($newRow['id']);
        $newRow['organization_id'] = $targetOrgId;
        if (array_key_exists('user_id', $newRow)) {
            $newRow['user_id'] = $targetUserId;
        }
        if (array_key_exists('uuid', $newRow)) {
            $newRow['uuid'] = (string) Str::uuid();
        }
        if (array_key_exists('asset_unique_uuid', $newRow)) {
            $newRow['asset_unique_uuid'] = (string) Str::uuid();
        }
        if (array_key_exists('deleted_at', $newRow)) {
            $newRow['deleted_at'] = null;
        }
        if (array_key_exists('created_at', $newRow)) {
            $newRow['created_at'] = now();
        }
        if (array_key_exists('updated_at', $newRow)) {
            $newRow['updated_at'] = now();
        }

        $newId = (int) DB::table($table)->insertGetId($newRow);

        return [$newId, $sourceRow, $newRow];
    }

    private function cloneAngleChildren(string $sourceUuid, string $targetUuid): void
    {
        if ($sourceUuid === '' || $targetUuid === '') {
            return;
        }

        $contents = DB::table('angle_contents')->where('angle_uuid', $sourceUuid)->get();
        foreach ($contents as $row) {
            $payload = (array) $row;
            unset($payload['id']);
            $payload['angle_uuid'] = $targetUuid;
            if (array_key_exists('uuid', $payload)) {
                $payload['uuid'] = (string) Str::uuid();
            }
            if (array_key_exists('created_at', $payload)) {
                $payload['created_at'] = now();
            }
            if (array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = now();
            }
            DB::table('angle_contents')->insert($payload);
        }

        $extraContents = DB::table('extra_contents')->where('angle_uuid', $sourceUuid)->get();
        foreach ($extraContents as $row) {
            $payload = (array) $row;
            unset($payload['id']);
            $payload['angle_uuid'] = $targetUuid;
            if (array_key_exists('created_at', $payload)) {
                $payload['created_at'] = now();
            }
            if (array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = now();
            }
            DB::table('extra_contents')->insert($payload);
        }
    }

    private function cloneAngleTemplateChildren(string $sourceUuid, string $targetUuid): void
    {
        if ($sourceUuid === '' || $targetUuid === '') {
            return;
        }

        $contents = DB::table('extra_contents')->where('angle_template_uuid', $sourceUuid)->get();
        foreach ($contents as $row) {
            $payload = (array) $row;
            unset($payload['id']);
            $payload['angle_template_uuid'] = $targetUuid;
            if (array_key_exists('created_at', $payload)) {
                $payload['created_at'] = now();
            }
            if (array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = now();
            }
            DB::table('extra_contents')->insert($payload);
        }
    }

    private function cloneUserApiInstanceValues(int $sourceInstanceId, int $targetInstanceId): void
    {
        $values = DB::table('user_api_instance_values')
            ->where('user_api_instance_id', $sourceInstanceId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($values as $row) {
            $payload = (array) $row;
            unset($payload['id']);
            $payload['user_api_instance_id'] = $targetInstanceId;
            if (array_key_exists('deleted_at', $payload)) {
                $payload['deleted_at'] = null;
            }
            if (array_key_exists('created_at', $payload)) {
                $payload['created_at'] = now();
            }
            if (array_key_exists('updated_at', $payload)) {
                $payload['updated_at'] = now();
            }
            DB::table('user_api_instance_values')->insert($payload);
        }
    }
}
