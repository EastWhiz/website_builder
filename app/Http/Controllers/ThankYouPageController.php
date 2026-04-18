<?php

namespace App\Http\Controllers;

use App\Models\ThankYouPage;
use App\Support\OrganizationAccess;
use App\Services\ThankYouPageImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

class ThankYouPageController extends Controller
{
    public function __construct(
        protected ThankYouPageImageService $imageService
    ) {
    }

    /**
     * List current user's thank you pages (for list page and export dropdown).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();
        $canViewOrgAll = $user
            ? Gate::forUser($user)->allows('org.permission', 'content.view_org_all')
            : false;
        $isPlatformAdmin = OrganizationAccess::isPrivilegedPlatformAdmin($user);

        $showPageOwnerColumn = $organization && $user
            && !OrganizationAccess::isPrivilegedPlatformAdmin($user)
            && OrganizationAccess::canUserFullyManageTeam($user, $organization);

        $thankYouPages = ThankYouPage::query()
            ->when($showPageOwnerColumn, fn ($q) => $q->with(['user:id,name']))
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->when(!$organization && !$isPlatformAdmin, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($organization && !$canViewOrgAll, fn ($q) => $q->where('user_id', (int) ($user?->id ?? 0)))
            ->orderBy('name')
            ->get()
            ->map(function (ThankYouPage $page) use ($showPageOwnerColumn) {
                $row = [
                    'id' => $page->id,
                    'user_id' => $page->user_id,
                    'name' => $page->name,
                    'title_text' => $page->title_text,
                    'logo_path' => $page->logo_path,
                    'logo_url' => $page->logo_url,
                    'profile_image_path' => $page->profile_image_path,
                    'profile_image_url' => $page->profile_image_url,
                    'hero_background_color' => $page->hero_background_color,
                ];
                if ($showPageOwnerColumn) {
                    $row['owner_name'] = $page->user?->name ?? '—';
                }

                return $row;
            });

        return Inertia::render('ThankYouPages/Index', [
            'thankYouPages' => $thankYouPages,
            'showThankYouPageOwnerColumn' => $showPageOwnerColumn,
        ]);
    }

    /**
     * API: List current user's thank you pages for export dropdowns.
     */
    public function apiIndex(Request $request)
    {
        $user = $request->user();
        $organization = $user?->currentOrganization();
        $canViewOrgAll = $user
            ? Gate::forUser($user)->allows('org.permission', 'content.view_org_all')
            : false;
        $isPlatformAdmin = OrganizationAccess::isPrivilegedPlatformAdmin($user);

        $pages = ThankYouPage::query()
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->when(!$organization && !$isPlatformAdmin, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($organization && !$canViewOrgAll, fn ($q) => $q->where('user_id', (int) ($user?->id ?? 0)))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $pages,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(): Response
    {
        return Inertia::render('ThankYouPages/Create');
    }

    /**
     * Store a new thank you page. Logo required on create.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title_text' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['required', 'image', 'max:5120'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
            'hero_background_color' => ['required', 'string', 'max:50'],
        ]);

        $page = ThankYouPage::create([
            'user_id' => $request->user()->id,
            'organization_id' => $request->user()?->currentOrganization()?->id,
            'name' => $validated['name'],
            'title_text' => $validated['title_text'],
            'description' => $validated['description'] ?? null,
            'hero_background_color' => $validated['hero_background_color'],
            'logo_path' => null,
            'profile_image_path' => null,
        ]);

        $logoPath = $this->imageService->saveLogo($page, $request->file('logo'));
        $page->update(['logo_path' => $logoPath]);

        if ($request->hasFile('profile_image')) {
            $profilePath = $this->imageService->saveProfileImage($page, $request->file('profile_image'));
            $page->update(['profile_image_path' => $profilePath]);
        }

        return Redirect::route('thank-you-pages.index')
            ->with('status', 'Thank you page created.');
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request, int $id): Response|RedirectResponse
    {
        $page = ThankYouPage::findOrFail($id);
        if ($page->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        return Inertia::render('ThankYouPages/Edit', [
            'thankYouPage' => [
                'id' => $page->id,
                'name' => $page->name,
                'title_text' => $page->title_text,
                'description' => $page->description,
                'logo_path' => $page->logo_path,
                'logo_url' => $page->logo_url,
                'profile_image_path' => $page->profile_image_path,
                'profile_image_url' => $page->profile_image_url,
                'hero_background_color' => $page->hero_background_color,
            ],
        ]);
    }

    /**
     * Update thank you page. Logo optional on update.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $page = ThankYouPage::findOrFail($id);
        if ($page->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title_text' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
            'hero_background_color' => ['required', 'string', 'max:50'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_profile_image' => ['nullable', 'boolean'],
        ]);

        if (filter_var($request->input('remove_logo'), FILTER_VALIDATE_BOOLEAN)) {
            $this->imageService->deleteLogo($page);
            $page->logo_path = null;
        } elseif ($request->hasFile('logo')) {
            $logoPath = $this->imageService->saveLogo($page, $request->file('logo'));
            $page->logo_path = $logoPath;
        }

        if (filter_var($request->input('remove_profile_image'), FILTER_VALIDATE_BOOLEAN)) {
            $this->imageService->deleteProfileImage($page);
            $page->profile_image_path = null;
        } elseif ($request->hasFile('profile_image')) {
            $profilePath = $this->imageService->saveProfileImage($page, $request->file('profile_image'));
            $page->profile_image_path = $profilePath;
        }

        $page->name = $validated['name'];
        $page->title_text = $validated['title_text'];
        $page->description = $validated['description'] ?? null;
        $page->hero_background_color = $validated['hero_background_color'];
        $page->save();

        return Redirect::route('thank-you-pages.index')
            ->with('status', 'Thank you page updated.');
    }

    /**
     * Delete thank you page (images deleted via model's deleting event).
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $page = ThankYouPage::findOrFail($id);
        if ($page->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized.');
        }

        $page->delete();

        return Redirect::route('thank-you-pages.index')
            ->with('status', 'Thank you page deleted.');
    }

    /**
     * Preview thank you page: return full HTML with custom logo, title, description, profile image, hero color.
     * No redirect or pixel scripts (preview-only).
     */
    public function preview(Request $request, int $id): View
    {
        $page = ThankYouPage::findOrFail($id);
        if (!$this->mayViewThankYouPage($request, $page)) {
            abort(403, 'Unauthorized.');
        }

        return view('thank_you_preview', [
            'logoUrl' => $page->logo_url,
            'profileImageUrl' => $page->profile_image_url,
            'titleText' => $page->title_text,
            'description' => $page->description ?? '',
            'heroBackgroundColor' => $page->hero_background_color ?: '#3B27A8',
        ]);
    }

    private function userOwnsThankYouPage(Request $request, ThankYouPage $page): bool
    {
        return (int) $page->user_id === (int) $request->user()->id;
    }

    /**
     * Preview: owner, or org viewer (same org + view all / org team admin).
     */
    private function mayViewThankYouPage(Request $request, ThankYouPage $page): bool
    {
        if ($this->userOwnsThankYouPage($request, $page)) {
            return true;
        }

        $user = $request->user();
        $org = $user?->currentOrganization();
        if (!$org || (int) ($page->organization_id ?? 0) !== (int) $org->id) {
            return false;
        }

        if (OrganizationAccess::isPrivilegedPlatformAdmin($user)) {
            return true;
        }

        if (Gate::forUser($user)->allows('org.permission', 'content.view_org_all')) {
            return true;
        }

        return OrganizationAccess::canUserFullyManageTeam($user, $org);
    }
}
