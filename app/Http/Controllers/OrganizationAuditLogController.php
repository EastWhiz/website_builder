<?php

namespace App\Http\Controllers;

use App\Models\OrganizationActivityLog;
use App\Support\OrganizationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return sendResponse(false, 'Unauthorized.', null, null, null, 401);
        }

        $canViewCrossOrg = Gate::forUser($user)->allows('org.permission', 'audit.view_cross_org');
        $canViewOrg = Gate::forUser($user)->allows('org.permission', 'audit.view_org');
        if (!$canViewCrossOrg && !$canViewOrg) {
            return sendResponse(false, 'You are not allowed to view audit logs.', null, null, null, 403);
        }

        $pageCount = (int) $request->get('page_count', 20);
        if ($pageCount <= 0) {
            $pageCount = 20;
        }
        if ($pageCount > 100) {
            $pageCount = 100;
        }

        $query = OrganizationActivityLog::query()
            ->with(['organization:id,name', 'actor:id,name,email']);

        if (!$canViewCrossOrg) {
            $organization = $user->currentOrganization();
            if (!$organization && !OrganizationAccess::isPrivilegedPlatformAdmin($user)) {
                return sendResponse(false, 'No organization context found for this user.', null, null, null, 422);
            }
            if ($organization) {
                $query->where('organization_id', (int) $organization->id);
            }
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'LIKE', '%' . $q . '%')
                    ->orWhereHas('organization', function ($orgQ) use ($q) {
                        $orgQ->where('name', 'LIKE', '%' . $q . '%');
                    })
                    ->orWhereHas('actor', function ($actorQ) use ($q) {
                        $actorQ->where('name', 'LIKE', '%' . $q . '%')
                            ->orWhere('email', 'LIKE', '%' . $q . '%');
                    });
            });
        }

        $action = trim((string) $request->get('action', ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $sort = trim((string) $request->get('sort', 'id desc'));
        if ($sort !== '') {
            [$col, $dir] = array_pad(explode(' ', $sort), 2, 'desc');
            $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
            if (!in_array($col, ['id', 'organization_id', 'action', 'created_at'], true)) {
                $col = 'id';
            }
            $query->orderBy($col, $dir);
        }

        $logs = $query->cursorPaginate($pageCount);

        $actionsQuery = OrganizationActivityLog::query();
        if (!$canViewCrossOrg) {
            $organization = $user->currentOrganization();
            if ($organization) {
                $actionsQuery->where('organization_id', (int) $organization->id);
            }
        }
        $actions = $actionsQuery
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return sendResponse(true, 'Audit logs retrieved successfully.', $logs, $actions);
    }
}
