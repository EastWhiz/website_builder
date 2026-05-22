<?php

namespace App\Http\Controllers;

use App\Models\Angle;
use App\Models\AngleContent;
use App\Models\AngleTemplate;
use App\Models\ExtraContent;
use App\Models\User;
use App\Support\OrganizationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $templates = User::query()
            ->leftJoin('organization_user as ou', function ($join) {
                $join->on('ou.user_id', '=', 'users.id')
                    ->whereNull('ou.deleted_at');
            })
            ->leftJoin('organizations as org', 'org.id', '=', 'ou.organization_id')
            ->leftJoin('roles as org_role', 'org_role.id', '=', 'ou.role_id')
            ->when($request->get('q'), function ($q) use ($request) {
                $search = '%' . $request->q . '%';
                $q->where(function ($q) use ($search) {
                    $q->where('users.name', 'LIKE', $search)
                        ->orWhere('users.email', 'LIKE', $search)
                        ->orWhere('org.name', 'LIKE', $search);
                });
            })
            ->when($request->get('sort'), function ($q) use ($request) {
                $sortParts = explode(' ', $request->get('sort'));
                $column = $sortParts[0] ?? 'users.id';
                $direction = strtolower($sortParts[1] ?? 'asc') === 'desc' ? 'desc' : 'asc';
                $allowedColumns = ['users.id', 'users.name', 'users.created_at', 'org.name'];
                if (!in_array($column, $allowedColumns, true)) {
                    $column = 'users.id';
                }
                $q->orderBy($column, $direction);
            }, function ($q) {
                $q->orderBy('users.id', 'asc');
            })
            ->select([
                'users.*',
                'ou.organization_id as current_organization_id',
                'ou.status as current_membership_status',
                'ou.role_id as current_organization_role_id',
                'org_role.key as current_organization_role_key',
                'org_role.name as current_organization_role_name',
                'org.name as current_organization_name',
            ])
            ->cursorPaginate($request->page_count);
        return sendResponse(true, 'Users retrieved successfully!', $templates);
    }

    public function userThemesList(Request $request, $id)
    {
        if (!$this->mayListLandingPagesForUser($request, (int) $id)) {
            return sendResponse(false, 'You are not allowed to view this user\'s landing pages.', null, null, null, 403);
        }

        $templates = AngleTemplate::where('user_id', $id)
            ->when($request->get('q'), function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->q . '%');
                    $q->orWhere('id', 'LIKE', '%' . $request->q . '%');
                });
            })
            ->when($request->get('sort'), function ($q) use ($request) {
                $q->orderBy(...explode(' ', $request->get('sort')));
            }, function ($q) {
                $q->orderBy('id', 'desc');
            })
            ->with(['user:id,name', 'template:id,name'])
            ->select(['id', 'name', 'created_at', 'user_id', 'template_id'])
            ->cursorPaginate($request->page_count);

        return sendResponse(true, 'Landing Pages retrieved successfully!', $templates);
    }

    /**
     * Org admins: all landing pages (angle templates) in the current organization.
     */
    public function organizationLandingPagesList(Request $request)
    {
        $user = $request->user();
        $org = $user?->currentOrganization();
        if (!$org) {
            return sendResponse(false, 'Organization context is required.', null, null, null, 422);
        }
        if (OrganizationAccess::isPrivilegedPlatformAdmin($user)) {
            return sendResponse(false, 'Unauthorized.', null, null, null, 403);
        }
        if (!OrganizationAccess::canUserFullyManageTeam($user, $org)) {
            return sendResponse(false, 'Unauthorized.', null, null, null, 403);
        }

        $pageCount = (int) $request->get('page_count', 10);
        if ($pageCount <= 0) {
            $pageCount = 10;
        }
        if ($pageCount > 100) {
            $pageCount = 100;
        }

        $memberUserIds = OrganizationAccess::activeOrganizationMemberUserIds($org);

        $templates = AngleTemplate::query()
            ->with(['user:id,name', 'template:id,name'])
            ->whereNull('deleted_at')
            ->where(function ($q) use ($org, $memberUserIds) {
                $q->where('organization_id', (int) $org->id);
                if ($memberUserIds !== []) {
                    $q->orWhereIn('user_id', $memberUserIds);
                }
            })
            ->when($request->get('q'), function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('name', 'LIKE', '%' . $request->q . '%')
                        ->orWhere('id', 'LIKE', '%' . $request->q . '%');
                });
            })
            ->when($request->get('sort'), function ($q) use ($request) {
                $q->orderBy(...explode(' ', $request->get('sort')));
            }, function ($q) {
                $q->orderBy('name', 'asc');
            })
            ->select(['id', 'name', 'created_at', 'user_id', 'template_id'])
            ->cursorPaginate($pageCount);

        return sendResponse(true, 'Organization landing pages retrieved successfully!', $templates);
    }

    private function mayListLandingPagesForUser(Request $request, int $targetUserId): bool
    {
        $actor = $request->user();
        if (!$actor) {
            return false;
        }
        if ((int) $actor->id === $targetUserId) {
            return true;
        }

        $org = $actor->currentOrganization();
        if (!$org) {
            return false;
        }
        if (!OrganizationAccess::isActiveOrganizationMember($targetUserId, (int) $org->id)) {
            return false;
        }
        if (OrganizationAccess::isPrivilegedPlatformAdmin($actor)) {
            return true;
        }

        return OrganizationAccess::canUserFullyManageTeam($actor, $org);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:' . User::class,
            'password' => 'required|string|min:8',
        ]);
        if ($validator->fails())
            return simpleValidate($validator);
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);
        return sendResponse(true, 'User created successfully!', $user);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:users,id',
        ]);
        if ($validator->fails())
            return simpleValidate($validator);

        $user = User::find($request->id);
        if ($user->role_id == 1) {
            return sendResponse(false, 'You cannot reset the password for Admin!');
        }

        $user->password = bcrypt('Reset@321');
        $user->save();
        return sendResponse(true, 'Password reset successfully!');
    }

    private function deleteUserAndRelatedData(User $user)
    {
        // Delete related angles templates and their contents
        $angleTemplates = AngleTemplate::where('user_id', $user->id)->get();
        foreach ($angleTemplates as $angleTemplate) {
            $extraContents = ExtraContent::where('angle_template_uuid', $angleTemplate->uuid)->get();
            $extraContents->each(function ($content) {
                Storage::disk('public')->deleteDirectory("angleTemplates/{$content->angle_template_uuid}");
                $content->delete();
            });
            $angleTemplate->delete();
        }

        // Delete related angles and their contents
        // $angles = Angle::where('user_id', $user->id)->get();
        // foreach ($angles as $angle) {
        //     Storage::disk('public')->deleteDirectory("angles/$angle->uuid");
        //     AngleContent::where('angle_uuid', $angle->uuid)->delete();
        //     $extraContents = ExtraContent::where('angle_uuid', $angle->uuid)->get();
        //     $extraContents->each(function ($content) {
        //         Storage::disk('public')->deleteDirectory("angleContents/{$content->angle_content_uuid}");
        //         $content->delete();
        //     });
        //     $angle->delete();
        // }
        $user->delete();
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:users,id',
        ]);

        if ($validator->fails())
            return simpleValidate($validator);

        $user = User::find($request->id);
        if ($user->role_id == 1) {
            return sendResponse(false, 'You cannot delete the Admin user!');
        }

        try {
            DB::beginTransaction();
            $this->deleteUserAndRelatedData($user);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return sendResponse(false, 'An error occurred while deleting the user: ' . $e->getMessage());
        }

        return sendResponse(true, 'User deleted successfully!');
    }
}
