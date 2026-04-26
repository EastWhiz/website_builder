<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApiCredentialsController;
use App\Models\ApiCategory;
use App\Models\UserApiInstance;
use App\Models\UserApiInstanceValue;
use App\Support\OrganizationAccess;
use App\Services\ApiInstanceValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserApiInstanceController extends Controller
{
    public function __construct(
        protected ApiInstanceValidationService $validationService
    ) {
    }

    private function denyUnlessAnyPermission(Request $request, array $permissionKeys)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        foreach ($permissionKeys as $permissionKey) {
            if (Gate::forUser($user)->allows('org.permission', $permissionKey)) {
                return null;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized action.',
        ], 403);
    }
    /**
     * List user's API instances, grouped by category.
     * Returns all instances (active and inactive) so the API Instance page (Profile) can display them and allow toggling.
     * Other consumers (e.g. form dropdowns in AngleTemplates/Angles) filter client-side to show only active.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();
        $canViewOrgAll = $user
            ? Gate::forUser($user)->allows('org.permission', 'content.view_org_all')
            : false;
        $isPlatformAdmin = OrganizationAccess::isPrivilegedPlatformAdmin($user);
        $orgAdminUserIds = $organization
            ? $this->organizationAdminUserIds((int) $organization->id)
            : [];

        $instances = UserApiInstance::query()
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->when(!$organization && !$isPlatformAdmin, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($organization && !$canViewOrgAll, function ($q) use ($user, $orgAdminUserIds) {
                $q->where(function ($scoped) use ($user, $orgAdminUserIds) {
                    $scoped->where('user_id', (int) ($user?->id ?? 0));
                    if (!empty($orgAdminUserIds)) {
                        $scoped->orWhereIn('user_id', $orgAdminUserIds);
                    }
                });
            })
            ->with(['category', 'values.field', 'user:id,name'])
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->orderBy('api_category_id')
            ->orderBy('name')
            ->get();

        $authUserId = (int) ($user?->id ?? 0);
        $grouped = $instances->groupBy('api_category_id')->map(function ($items) use ($authUserId) {
            $category = $items->first()->category;
            if (!$category || !$category->is_active) {
                return null;
            }
            return [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                'instances' => $items->map(fn ($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'is_active' => $i->is_active,
                    'credentials' => $i->credentials,
                    'owner_user_id' => (int) ($i->user_id ?? 0),
                    'owner_name' => trim((string) ($i->user?->name ?? '')),
                    'is_owned_by_current_user' => (int) ($i->user_id ?? 0) === $authUserId,
                ])->values()->all(),
            ];
        })->filter()->values()->all();

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Create new API instance.
     */
    public function store(Request $request)
    {
        if ($denied = $this->denyUnlessAnyPermission($request, ['integration.instance.create'])) {
            return $denied;
        }

        $validated = $request->validate([
            'api_category_id' => 'required|integer|exists:api_categories,id',
            'name' => 'required|string|max:255',
            'values' => 'required|array',
        ]);

        $category = ApiCategory::active()->with('fields')->findOrFail($validated['api_category_id']);
        $validator = $this->validationService->validate($request->all(), $category);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $values = $validated['values'];

        $instance = UserApiInstance::create([
            'user_id' => Auth::id(),
            'organization_id' => $request->user()?->currentOrganization()?->id,
            'api_category_id' => $category->id,
            'name' => $validated['name'],
            'is_active' => true,
        ]);

        foreach ($category->fields as $field) {
            $value = $values[$field->name] ?? null;
            if ($value !== null && $value !== '') {
                $val = new UserApiInstanceValue();
                $val->user_api_instance_id = $instance->id;
                $val->api_category_field_id = $field->id;
                $val->setRelation('field', $field);
                $val->value = $value;
                $val->save();
            }
        }

        // Reload from DB and load full relations (same as update) so CRM sync gets complete payload
        $instance->refresh();
        $instance->load(['category.fields', 'values.field']);

        app(ApiCredentialsController::class)->syncToExternalApiFromInstance($instance);

        return response()->json([
            'success' => true,
            'message' => 'API instance created successfully.',
            'data' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'is_active' => $instance->is_active,
                'credentials' => $instance->credentials,
            ],
        ], 201);
    }

    /**
     * Get API instance details (user must own it).
     */
    public function show($id)
    {
        $instance = UserApiInstance::with(['category', 'values.field'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'is_active' => $instance->is_active,
                'api_category_id' => $instance->api_category_id,
                'category' => ['id' => $instance->category->id, 'name' => $instance->category->name],
                'credentials' => $instance->credentials,
            ],
        ]);
    }

    /**
     * Update API instance (user must own it).
     */
    public function update(Request $request, $id)
    {
        if ($denied = $this->denyUnlessAnyPermission($request, ['integration.instance.update'])) {
            return $denied;
        }

        $instance = UserApiInstance::with('category.fields')
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'values' => 'sometimes|array',
        ]);

        if (isset($validated['name'])) {
            $instance->update(['name' => $validated['name']]);
        }

        if (isset($validated['values'])) {
            $values = $validated['values'];
            $validator = $this->validationService->validate($request->all(), $instance->category);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            foreach ($instance->category->fields as $field) {
                $value = $values[$field->name] ?? null;
                $record = UserApiInstanceValue::firstOrNew([
                    'user_api_instance_id' => $instance->id,
                    'api_category_field_id' => $field->id,
                ]);
                $record->setRelation('field', $field);
                $record->value = $value ?? '';
                $record->save();
            }
        }

        // Reload instance and relations from DB so CRM sync sends the latest credentials
        $instance->refresh();
        $instance->load(['category.fields', 'values.field']);

        app(ApiCredentialsController::class)->syncToExternalApiFromInstance($instance);

        return response()->json([
            'success' => true,
            'message' => 'API instance updated successfully.',
            'data' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'is_active' => $instance->is_active,
                'credentials' => $instance->credentials,
            ],
        ]);
    }

    /**
     * Delete API instance (user must own it).
     */
    public function destroy($id)
    {
        $request = request();
        if ($denied = $this->denyUnlessAnyPermission($request, ['integration.instance.soft_del', 'integration.instance.soft_delete'])) {
            return $denied;
        }

        $instance = UserApiInstance::where('user_id', Auth::id())->findOrFail($id);
        // Inform external CRM before soft-deleting locally
        app(ApiCredentialsController::class)->deleteFromExternalApiFromInstance($instance);
        $instance->delete();

        return response()->json([
            'success' => true,
            'message' => 'API instance deleted successfully.',
        ]);
    }

    /**
     * Toggle active status (user must own it).
     */
    public function toggleActive($id)
    {
        $request = request();
        if ($denied = $this->denyUnlessAnyPermission($request, ['integration.instance.update'])) {
            return $denied;
        }

        $instance = UserApiInstance::where('user_id', Auth::id())->findOrFail($id);
        $instance->update(['is_active' => !$instance->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'API instance updated successfully.',
            'data' => ['is_active' => $instance->fresh()->is_active],
        ]);
    }

    /**
     * List active API categories with fields (for profile / instance creation).
     */
    public function categories()
    {
        $categories = ApiCategory::active()
            ->with('fields')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get user's instances for a category (active + inactive).
     */
    public function getByCategory($categoryId)
    {
        $user = Auth::user();
        $organization = $user?->currentOrganization();
        $canViewOrgAll = $user
            ? Gate::forUser($user)->allows('org.permission', 'content.view_org_all')
            : false;
        $isPlatformAdmin = OrganizationAccess::isPrivilegedPlatformAdmin($user);
        $orgAdminUserIds = $organization
            ? $this->organizationAdminUserIds((int) $organization->id)
            : [];

        $category = ApiCategory::query()->find($categoryId);
        if (!$category) {
            return response()->json(['success' => true, 'data' => []]);
        }
        $instances = UserApiInstance::query()
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->when(!$organization && !$isPlatformAdmin, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($organization && !$canViewOrgAll, function ($q) use ($user, $orgAdminUserIds) {
                $q->where(function ($scoped) use ($user, $orgAdminUserIds) {
                    $scoped->where('user_id', (int) ($user?->id ?? 0));
                    if (!empty($orgAdminUserIds)) {
                        $scoped->orWhereIn('user_id', $orgAdminUserIds);
                    }
                });
            })
            ->with(['category', 'values.field', 'user:id,name'])
            ->where('api_category_id', $categoryId)
            ->orderBy('name')
            ->get();

        $authUserId = (int) ($user?->id ?? 0);

        return response()->json([
            'success' => true,
            'data' => $instances->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'is_active' => $i->is_active,
                'credentials' => $i->credentials,
                'category_name' => $i->category?->name,
                'owner_user_id' => (int) ($i->user_id ?? 0),
                'owner_name' => trim((string) ($i->user?->name ?? '')),
                'is_owned_by_current_user' => (int) ($i->user_id ?? 0) === $authUserId,
            ]),
        ]);
    }

    /**
     * Resolve active organization admin user IDs for an organization.
     *
     * @return array<int, int>
     */
    private function organizationAdminUserIds(int $organizationId): array
    {
        return \Illuminate\Support\Facades\DB::table('organization_user as ou')
            ->join('roles as r', 'r.id', '=', 'ou.role_id')
            ->where('ou.organization_id', $organizationId)
            ->whereNull('ou.deleted_at')
            ->where('ou.status', 'active')
            ->where('r.key', 'org_admin')
            ->pluck('ou.user_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
