<?php

namespace App\Http\Controllers;

use App\Models\ThankYouPage;
use App\Services\ThankYouPageImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
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
        $thankYouPages = $request->user()
            ->thankYouPages()
            ->orderBy('name')
            ->get()
            ->map(fn (ThankYouPage $page) => [
                'id' => $page->id,
                'name' => $page->name,
                'title_text' => $page->title_text,
                'logo_path' => $page->logo_path,
                'logo_url' => $page->logo_url,
                'profile_image_path' => $page->profile_image_path,
                'profile_image_url' => $page->profile_image_url,
                'hero_background_color' => $page->hero_background_color,
            ]);

        return Inertia::render('ThankYouPages/Index', [
            'thankYouPages' => $thankYouPages,
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
        ]);

        if ($request->hasFile('logo')) {
            $logoPath = $this->imageService->saveLogo($page, $request->file('logo'));
            $page->logo_path = $logoPath;
        }
        if ($request->hasFile('profile_image')) {
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
        if ($page->user_id !== $request->user()->id) {
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
}
