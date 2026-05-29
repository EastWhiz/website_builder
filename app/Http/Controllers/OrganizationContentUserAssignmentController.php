<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignOrganizationContentToUserRequest;
use App\Http\Requests\CloneAngleTemplateToUserRequest;
use App\Http\Requests\CloneAngleTemplatesToUserRequest;
use App\Http\Requests\CloneThankYouPageToUserRequest;
use App\Models\AngleTemplate;
use App\Models\Organization;
use App\Models\ThankYouPage;
use App\Services\AngleTemplateCloneService;
use App\Services\OrganizationActivityLogger;
use App\Services\ThankYouPageImageService;
use App\Support\OrganizationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationContentUserAssignmentController extends Controller
{
    /** @var array<string, string> */
    private const TABLES = [
        'angle' => 'angles',
        'angle_template' => 'angle_templates',
        'thank_you_page' => 'thank_you_pages',
        'user_api_instance' => 'user_api_instances',
    ];

    public function __construct(
        private readonly OrganizationActivityLogger $activityLogger,
        private readonly ThankYouPageImageService $thankYouPageImageService,
        private readonly AngleTemplateCloneService $angleTemplateCloneService
    ) {
    }

    /**
     * Step 4.3: Assign org-scoped content to a member inside the same organization.
     */
    public function assignToUser(AssignOrganizationContentToUserRequest $request)
    {
        $user = $request->user();
        $targetUserId = (int) $request->input('to_user_id');
        $organizationId = $this->resolveOrganizationId($request);
        if ($organizationId instanceof \Illuminate\Http\JsonResponse) {
            return $organizationId;
        }

        if (!$this->isActiveOrgMember($targetUserId, $organizationId)) {
            return sendResponse(false, 'Selected user is not an active member of this organization.', null, null, null, 422);
        }

        if (!$this->mayAssignWithinOrganization($user, $organizationId)) {
            return sendResponse(false, 'You are not allowed to assign content in this organization.', null, null, null, 403);
        }

        $items = $this->normalizeItems((array) $request->input('items', []));
        if ($items === []) {
            return sendResponse(false, 'No valid items to assign.', null, null, null, 422);
        }

        $countsByType = [];
        $assignedItems = [];

        try {
            DB::transaction(function () use ($items, $targetUserId, $organizationId, &$countsByType, &$assignedItems) {
                foreach ($items as $item) {
                    $type = $item['type'];
                    $id = $item['id'];
                    $table = self::TABLES[$type];

                    $record = DB::table($table)
                        ->where('id', $id)
                        ->where('organization_id', $organizationId)
                        ->whereNull('deleted_at')
                        ->first(['id', 'user_id']);

                    if (!$record) {
                        throw new \RuntimeException("Could not assign {$type} #{$id}. It is not available in this organization.");
                    }

                    $updated = DB::table($table)
                        ->where('id', $id)
                        ->update([
                            'user_id' => $targetUserId,
                            'updated_at' => now(),
                        ]);

                    if ($updated !== 1) {
                        throw new \RuntimeException("Could not assign {$type} #{$id}.");
                    }

                    $countsByType[$type] = ($countsByType[$type] ?? 0) + 1;
                    $assignedItems[] = [
                        'type' => $type,
                        'id' => $id,
                        'from_user_id' => (int) ($record->user_id ?? 0),
                        'to_user_id' => $targetUserId,
                    ];
                }
            });
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, null, null, 422);
        } catch (\Throwable $e) {
            return sendResponse(false, 'In-organization assignment failed.', null, null, null, 500);
        }

        $this->activityLogger->logMoveOrClone(
            $organizationId,
            'org.content.assign_to_user_in_org',
            'organization_content_batch',
            null,
            $organizationId,
            $organizationId,
            [
                'to_user_id' => $targetUserId,
                'counts_by_type' => $countsByType,
                'items' => $assignedItems,
            ]
        );

        return sendResponse(true, 'Content assigned to user successfully.', [
            'organization_id' => $organizationId,
            'to_user_id' => $targetUserId,
            'counts_by_type' => $countsByType,
            'items' => $assignedItems,
        ]);
    }

    /**
     * Org admins (and platform super admins): duplicate a thank you page to another org member's account.
     */
    public function cloneThankYouPageToUser(CloneThankYouPageToUserRequest $request)
    {
        $actor = $request->user();
        $organizationId = $this->resolveOrganizationId($request);
        if ($organizationId instanceof \Illuminate\Http\JsonResponse) {
            return $organizationId;
        }

        $organization = Organization::find($organizationId);
        if (!$organization || !OrganizationAccess::canUserFullyManageTeam($actor, $organization)) {
            return sendResponse(false, 'You are not allowed to clone thank you pages for this organization.', null, null, null, 403);
        }

        $targetUserId = (int) $request->input('to_user_id');
        $pageId = (int) $request->input('thank_you_page_id');

        if (!$this->isActiveOrgMember($targetUserId, $organizationId)) {
            return sendResponse(false, 'Selected user is not an active member of this organization.', null, null, null, 422);
        }

        $source = ThankYouPage::query()
            ->where('id', $pageId)
            ->where('organization_id', $organizationId)
            ->whereNull('deleted_at')
            ->first();

        if (!$source) {
            return sendResponse(false, 'Thank you page not found in this organization.', null, null, null, 422);
        }

        $newPage = null;

        try {
            DB::transaction(function () use ($source, $targetUserId, $organizationId, &$newPage): void {
                $newPage = ThankYouPage::create([
                    'user_id' => $targetUserId,
                    'organization_id' => $organizationId,
                    'name' => $source->name,
                    'title_text' => $source->title_text,
                    'description' => $source->description,
                    'hero_background_color' => $source->hero_background_color,
                    'logo_path' => null,
                    'profile_image_path' => null,
                    'template_type' => $source->template_type,
                    'v2_content' => $source->v2_content,
                    'facebook_pixel_url' => $source->facebook_pixel_url,
                    'second_pixel_url' => $source->second_pixel_url,
                ]);

                $paths = $this->thankYouPageImageService->copyImagesFromPage($source, $newPage);
                $paths = array_filter($paths, fn ($v) => $v !== null);
                if ($paths !== []) {
                    $newPage->update($paths);
                }
            });
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, null, null, 422);
        } catch (\Throwable $e) {
            return sendResponse(false, 'Could not clone thank you page.', null, null, null, 500);
        }

        $this->activityLogger->logMoveOrClone(
            $organizationId,
            'org.content.clone_thank_you_page_to_user',
            'thank_you_page',
            (string) ($newPage?->id ?? ''),
            $organizationId,
            $organizationId,
            [
                'source_thank_you_page_id' => $pageId,
                'to_user_id' => $targetUserId,
                'new_thank_you_page_id' => $newPage?->id,
            ]
        );

        return sendResponse(true, 'Thank you page cloned to user successfully.', [
            'organization_id' => $organizationId,
            'to_user_id' => $targetUserId,
            'thank_you_page_id' => $newPage?->id,
        ]);
    }

    /**
     * Org primary / org_admin: clone a landing page (angle template) to another org member.
     */
    public function cloneAngleTemplateToUser(CloneAngleTemplateToUserRequest $request)
    {
        $ctx = $this->authorizeOrgLandingClone($request);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['organization' => $organization, 'organizationId' => $organizationId, 'memberUserIds' => $memberUserIds] = $ctx;

        $targetUserId = (int) $request->input('to_user_id');
        $templateId = (int) $request->input('angle_template_id');

        if (!$this->isActiveOrgMember($targetUserId, $organizationId)) {
            return sendResponse(false, 'Selected user is not an active member of this organization.', null, null, null, 422);
        }

        $source = $this->findCloneableAngleTemplateForOrg($templateId, $organizationId, $memberUserIds);
        if (!$source) {
            return sendResponse(false, 'Landing page not found in this organization.', null, null, null, 422);
        }

        try {
            $newTemplate = $this->angleTemplateCloneService->cloneIntoOrgForUser($source, $targetUserId, $organizationId);
        } catch (\Throwable $e) {
            return sendResponse(false, 'Could not clone landing page.', null, null, null, 500);
        }

        $this->activityLogger->logMoveOrClone(
            $organizationId,
            'org.content.clone_angle_template_to_user',
            'angle_template',
            (string) $newTemplate->id,
            $organizationId,
            $organizationId,
            [
                'source_angle_template_id' => $templateId,
                'to_user_id' => $targetUserId,
                'new_angle_template_id' => $newTemplate->id,
            ]
        );

        return sendResponse(true, 'Landing page cloned to user successfully.', [
            'organization_id' => $organizationId,
            'to_user_id' => $targetUserId,
            'angle_template_id' => $newTemplate->id,
        ]);
    }

    /**
     * Clone multiple landing pages (angle templates) to one org member in a single transaction.
     */
    public function cloneAngleTemplatesToUser(CloneAngleTemplatesToUserRequest $request)
    {
        $ctx = $this->authorizeOrgLandingClone($request);
        if ($ctx instanceof \Illuminate\Http\JsonResponse) {
            return $ctx;
        }
        ['organizationId' => $organizationId, 'memberUserIds' => $memberUserIds] = $ctx;

        $targetUserId = (int) $request->input('to_user_id');
        $rawIds = (array) $request->input('angle_template_ids', []);
        $templateIds = array_values(array_unique(array_filter(array_map('intval', $rawIds), fn (int $id) => $id > 0)));

        if ($templateIds === []) {
            return sendResponse(false, 'No valid landing page ids to clone.', null, null, null, 422);
        }

        if (!$this->isActiveOrgMember($targetUserId, $organizationId)) {
            return sendResponse(false, 'Selected user is not an active member of this organization.', null, null, null, 422);
        }

        $newIds = [];

        try {
            DB::transaction(function () use ($templateIds, $targetUserId, $organizationId, $memberUserIds, &$newIds): void {
                foreach ($templateIds as $templateId) {
                    $source = $this->findCloneableAngleTemplateForOrg($templateId, $organizationId, $memberUserIds);
                    if (!$source) {
                        throw new \RuntimeException("Landing page #{$templateId} was not found in this organization.");
                    }
                    $new = $this->angleTemplateCloneService->cloneIntoOrgForUser($source, $targetUserId, $organizationId);
                    $newIds[] = $new->id;
                }
            });
        } catch (\RuntimeException $e) {
            return sendResponse(false, $e->getMessage(), null, null, null, 422);
        } catch (\Throwable $e) {
            return sendResponse(false, 'Could not clone landing pages.', null, null, null, 500);
        }

        $this->activityLogger->logMoveOrClone(
            $organizationId,
            'org.content.clone_angle_templates_to_user',
            'organization_content_batch',
            null,
            $organizationId,
            $organizationId,
            [
                'to_user_id' => $targetUserId,
                'source_angle_template_ids' => $templateIds,
                'new_angle_template_ids' => $newIds,
                'count' => count($newIds),
            ]
        );

        return sendResponse(true, count($newIds) . ' landing page(s) cloned to user successfully.', [
            'organization_id' => $organizationId,
            'to_user_id' => $targetUserId,
            'angle_template_ids' => $newIds,
            'count' => count($newIds),
        ]);
    }

    /**
     * @return array{organization: Organization, organizationId: int, memberUserIds: list<int>}|JsonResponse
     */
    private function authorizeOrgLandingClone(Request $request): array|\Illuminate\Http\JsonResponse
    {
        $actor = $request->user();
        $organizationId = $this->resolveOrganizationId($request);
        if ($organizationId instanceof \Illuminate\Http\JsonResponse) {
            return $organizationId;
        }

        $organization = Organization::find($organizationId);
        if (!$organization
            || OrganizationAccess::isPrivilegedPlatformAdmin($actor)
            || !OrganizationAccess::canUserFullyManageTeam($actor, $organization)) {
            return sendResponse(false, 'You are not allowed to clone landing pages for this organization.', null, null, null, 403);
        }

        $memberUserIds = OrganizationAccess::activeOrganizationMemberUserIds($organization);

        return [
            'organization' => $organization,
            'organizationId' => $organizationId,
            'memberUserIds' => $memberUserIds,
        ];
    }

    /**
     * @param  list<int>  $memberUserIds
     */
    private function findCloneableAngleTemplateForOrg(int $templateId, int $organizationId, array $memberUserIds): ?AngleTemplate
    {
        return AngleTemplate::query()
            ->where('id', $templateId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($organizationId, $memberUserIds) {
                $q->where('organization_id', $organizationId);
                if ($memberUserIds !== []) {
                    $q->orWhereIn('user_id', $memberUserIds);
                }
            })
            ->first();
    }

    private function mayAssignWithinOrganization(?\App\Models\User $actor, int $organizationId): bool
    {
        if (!$actor) {
            return false;
        }
        if (OrganizationAccess::isPrivilegedPlatformAdmin($actor)) {
            return true;
        }

        $actorOrg = $actor->currentOrganization();
        if (!$actorOrg || (int) $actorOrg->id !== $organizationId) {
            return false;
        }

        return true;
    }

    private function resolveOrganizationId(Request $request): int|\Illuminate\Http\JsonResponse
    {
        $requestedOrgId = $request->input('organization_id');
        $actor = $request->user();

        if ($requestedOrgId !== null && $requestedOrgId !== '') {
            $orgId = (int) $requestedOrgId;
            if (!OrganizationAccess::isPrivilegedPlatformAdmin($actor)) {
                $current = $actor?->currentOrganization();
                if (!$current || (int) $current->id !== $orgId) {
                    return sendResponse(false, 'You can assign content only within your own organization.', null, null, null, 403);
                }
            }
            return $orgId;
        }

        $current = $actor?->currentOrganization();
        if ($current) {
            return (int) $current->id;
        }

        return sendResponse(false, 'Organization context is required.', null, null, null, 422);
    }

    private function isActiveOrgMember(int $userId, int $organizationId): bool
    {
        return OrganizationAccess::isActiveOrganizationMember($userId, $organizationId);
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
