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
     * Step 4.2: Super Admin assigns existing content to organization pool only.
     * This does not change user assignment. It only updates organization_id.
     */
    public function assignToOrganization(AssignContentToOrganizationRequest $request)
    {
        $validated = $request->validated();
        $organizationId = (int) $validated['organization_id'];
        $items = $this->normalizeItems((array) ($validated['items'] ?? []));

        if ($items === []) {
            return sendResponse(false, 'No valid items to assign.', null, null, null, 422);
        }

        $countsByType = [];
        $updatedItems = [];

        try {
            DB::transaction(function () use ($items, $organizationId, &$countsByType, &$updatedItems) {
                foreach ($items as $item) {
                    $type = $item['type'];
                    $id = $item['id'];
                    $table = self::TABLES[$type];

                    $updated = DB::table($table)
                        ->where('id', $id)
                        ->whereNull('deleted_at')
                        ->update([
                            'organization_id' => $organizationId,
                            'updated_at' => now(),
                        ]);

                    if ($updated !== 1) {
                        throw new \RuntimeException("Could not assign {$type} #{$id}. It may be missing or archived.");
                    }

                    $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;
                    $updatedItems[] = ['type' => $type, 'id' => $id];
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
}
