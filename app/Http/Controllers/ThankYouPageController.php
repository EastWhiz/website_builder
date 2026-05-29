<?php

namespace App\Http\Controllers;

use App\Models\ThankYouPage;
use App\Support\OrganizationAccess;
use App\Services\DeepLService;
use App\Services\ThankYouPageImageService;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
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
                    'template_type' => $page->template_type ?: ThankYouPage::TEMPLATE_TYPE_LEGACY,
                    'v2_content' => $page->v2_content ?? [],
                ];
                if ($showPageOwnerColumn) {
                    $row['owner_name'] = $page->user?->name ?? 'Ã¢â‚¬â€';
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'template_type']);

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
        // New Thank You pages are V2 only. Legacy remains supported for existing pages.
        $templateType = ThankYouPage::TEMPLATE_TYPE_GEO_AWARE_V2;
        $request->merge(['template_type' => $templateType]);
        $isLegacy = false;
        $validated = $request->validate($this->thankYouValidationRules($isLegacy, false));
        $v2Content = $this->normalizeGeoAwareV2Content($this->extractV2Content($validated));
        $pixelUrls = ThankYouPage::pixelUrlsFromUser($request->user());

        $page = ThankYouPage::create([
            'user_id' => $request->user()->id,
            'organization_id' => $request->user()?->currentOrganization()?->id,
            'name' => $validated['name'],
            'title_text' => $isLegacy ? $validated['title_text'] : ($validated['v2_banner_heading'] ?? 'Thank You'),
            'description' => $isLegacy ? ($validated['description'] ?? null) : ($validated['v2_banner_text_1'] ?? null),
            'hero_background_color' => $validated['hero_background_color'],
            'logo_path' => null,
            'profile_image_path' => null,
            'template_type' => $validated['template_type'] ?? ThankYouPage::TEMPLATE_TYPE_LEGACY,
            'v2_content' => $v2Content,
            'facebook_pixel_url' => $pixelUrls['facebook_pixel_url'],
            'second_pixel_url' => $pixelUrls['second_pixel_url'],
        ]);

        if ($request->hasFile('logo')) {
            $logoPath = $this->imageService->saveLogo($page, $request->file('logo'));
            $page->update(['logo_path' => $logoPath]);
        }

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
                'template_type' => $page->template_type ?: ThankYouPage::TEMPLATE_TYPE_LEGACY,
                'v2_content' => $page->v2_content ?? [],
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

        $templateType = (string) $request->input('template_type', $page->template_type ?: ThankYouPage::TEMPLATE_TYPE_LEGACY);
        $isLegacy = $templateType !== ThankYouPage::TEMPLATE_TYPE_GEO_AWARE_V2;
        $validated = $request->validate($this->thankYouValidationRules($isLegacy, true));
        $v2Content = $this->extractV2Content($validated);

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
        $page->title_text = $isLegacy ? $validated['title_text'] : ($validated['v2_banner_heading'] ?? $page->title_text);
        $page->description = $isLegacy ? ($validated['description'] ?? null) : ($validated['v2_banner_text_1'] ?? null);
        $page->hero_background_color = $validated['hero_background_color'];
        $page->template_type = $validated['template_type'] ?? ThankYouPage::TEMPLATE_TYPE_LEGACY;
        if (!$isLegacy) {
            $existingV2 = is_array($page->v2_content) ? $page->v2_content : [];
            $page->v2_content = $this->normalizeGeoAwareV2Content(array_merge($existingV2, $v2Content));
        } else {
            $page->v2_content = $v2Content;
        }
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
    public function preview(Request $request, int $id): View|HttpResponse
    {
        $page = ThankYouPage::findOrFail($id);
        if (!$this->mayViewThankYouPage($request, $page)) {
            abort(403, 'Unauthorized.');
        }

        if (($page->template_type ?: ThankYouPage::TEMPLATE_TYPE_LEGACY) === ThankYouPage::TEMPLATE_TYPE_GEO_AWARE_V2) {
            return response($this->renderGeoAwareV2Preview($page), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return view('thank_you_preview', [
            'logoUrl' => $page->logo_url,
            'profileImageUrl' => $page->profile_image_url,
            'titleText' => $page->title_text,
            'description' => $page->description ?? '',
            'heroBackgroundColor' => $page->hero_background_color ?: '#3B27A8',
        ]);
    }

    /**
     * Translate a Thank You page by creating a separate translated variant.
     */
    public function translate(Request $request)
    {
        $request->validate([
            'thank_you_page_id' => ['required', 'integer', 'exists:thank_you_pages,id'],
            'target_language' => ['required', 'string', 'max:20'],
            'split_sentences' => ['nullable'],
            'preserve_formatting' => ['nullable'],
        ]);

        $page = ThankYouPage::findOrFail((int) $request->input('thank_you_page_id'));
        if ((int) $page->user_id !== (int) $request->user()->id) {
            return sendResponse(false, "You don't have permission to translate this page", null);
        }

        $apiKey = trim((string) ($request->user()->deepl_api_key ?? ''));
        if ($apiKey === '') {
            return sendResponse(false, 'DeepL API key is required. Please add your DeepL API key in Profile Ã¢â€ â€™ DeepL API Key Section.', null);
        }

        try {
            $deepLService = new DeepLService($apiKey);
            $targetLanguage = strtoupper((string) $request->input('target_language'));
            $splitSentences = $request->input('split_sentences');
            $preserveFormatting = $request->input('preserve_formatting');

            if (($page->template_type ?: ThankYouPage::TEMPLATE_TYPE_LEGACY) === ThankYouPage::TEMPLATE_TYPE_GEO_AWARE_V2) {
                $translated = $this->translateGeoAwareV2Page($page, $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
            } else {
                $translated = $this->translateLegacyPage($page, $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
            }

            return sendResponse(true, "Thank you page translated successfully to {$targetLanguage}.", $translated);
        } catch (\Throwable $e) {
            Log::error('Thank You page translation failed', [
                'page_id' => $request->input('thank_you_page_id'),
                'error' => $e->getMessage(),
            ]);

            return sendResponse(false, 'Translation failed. Please try again.', null);
        }
    }

    private function userOwnsThankYouPage(Request $request, ThankYouPage $page): bool
    {
        return (int) $page->user_id === (int) $request->user()->id;
    }

    private function translateLegacyPage(
        ThankYouPage $source,
        string $targetLanguage,
        DeepLService $deepLService,
        $splitSentences = null,
        $preserveFormatting = null
    ): ThankYouPage {
        $title = $this->translatePlainText((string) $source->title_text, $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
        $description = $this->translatePlainText((string) ($source->description ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);

        return ThankYouPage::create([
            'user_id' => (int) $source->user_id,
            'organization_id' => $source->organization_id,
            'name' => $this->translatedName((string) $source->name, $targetLanguage),
            'logo_path' => $source->logo_path,
            'title_text' => $title,
            'description' => $description,
            'profile_image_path' => $source->profile_image_path,
            'hero_background_color' => $source->hero_background_color,
            'template_type' => ThankYouPage::TEMPLATE_TYPE_LEGACY,
            'v2_content' => null,
            'facebook_pixel_url' => $source->facebook_pixel_url,
            'second_pixel_url' => $source->second_pixel_url,
        ]);
    }

    private function translateGeoAwareV2Page(
        ThankYouPage $source,
        string $targetLanguage,
        DeepLService $deepLService,
        $splitSentences = null,
        $preserveFormatting = null
    ): ThankYouPage {
        $v2 = $this->normalizeGeoAwareV2Content(
            is_array($source->v2_content) ? $source->v2_content : []
        );
        $translatableKeys = [
            'v2_page_title',
            'v2_top_strip_text',
            'v2_banner_limited_text',
            'v2_banner_heading',
            'v2_banner_text_1',
            'v2_banner_text_2',
            'v2_call_scheduled_text',
            'v2_call_setup_text',
            'v2_what_happens_heading',
            'v2_footer_text',
            'v2_registration_prefix',
            'v2_scheduled_label_today',
            'v2_scheduled_label_tomorrow',
            'v2_why_wait_heading',
            'v2_why_wait_text_1',
            'v2_why_wait_text_2',
            'v2_why_wait_quote',
            'v2_customer_reviews_heading',
        ];

        foreach ($translatableKeys as $key) {
            $v2[$key] = $this->translatePlainText((string) ($v2[$key] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
        }

        // Translate structured "What Happens Next" and footer payloads (stored as JSON-like arrays in v2_content)
        $what = $this->normalizeWhatHappensNextPayload($v2['v2_what_happens_next'] ?? null);
        $what['heading'] = $this->translatePlainText((string) ($what['heading'] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
        foreach ($what['steps'] as $idx => $step) {
            $what['steps'][$idx]['title'] = $this->translatePlainText((string) ($step['title'] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
            $what['steps'][$idx]['description'] = $this->translatePlainText((string) ($step['description'] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
        }
        $v2['v2_what_happens_next'] = $what;
        $v2['v2_what_happens_heading'] = $what['heading'];

        $footer = $this->normalizeFooterPayload($v2['v2_footer'] ?? null);
        $footer['copyright'] = $this->translatePlainText((string) ($footer['copyright'] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
        $v2['v2_footer'] = $footer;
        $v2['v2_footer_text'] = $footer['copyright'];

        $reviews = $this->normalizeReviewsPayload($v2['v2_reviews'] ?? null);
        foreach ($reviews['items'] as $idx => $item) {
            $reviews['items'][$idx]['quote'] = $this->translatePlainText((string) ($item['quote'] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
            $reviews['items'][$idx]['author'] = $this->translatePlainText((string) ($item['author'] ?? ''), $targetLanguage, $deepLService, $splitSentences, $preserveFormatting);
        }
        $v2['v2_reviews'] = $reviews;

        $title = trim((string) ($v2['v2_banner_heading'] ?? $source->title_text));
        $desc = trim((string) ($v2['v2_banner_text_1'] ?? $source->description));

        return ThankYouPage::create([
            'user_id' => (int) $source->user_id,
            'organization_id' => $source->organization_id,
            'name' => $this->translatedName((string) $source->name, $targetLanguage),
            'logo_path' => $source->logo_path,
            'title_text' => $title,
            'description' => $desc,
            'profile_image_path' => $source->profile_image_path,
            'hero_background_color' => $source->hero_background_color,
            'template_type' => ThankYouPage::TEMPLATE_TYPE_GEO_AWARE_V2,
            'v2_content' => $v2,
            'facebook_pixel_url' => $source->facebook_pixel_url,
            'second_pixel_url' => $source->second_pixel_url,
        ]);
    }

    private function translatePlainText(
        string $text,
        string $targetLanguage,
        DeepLService $deepLService,
        $splitSentences = null,
        $preserveFormatting = null
    ): string {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        $translated = $deepLService->translate($trimmed, $targetLanguage, null, $splitSentences, $preserveFormatting);
        return is_string($translated) && trim($translated) !== '' ? $translated : $text;
    }

    private function translatedName(string $name, string $targetLanguage): string
    {
        $suffix = ' (' . strtoupper($targetLanguage) . ')';
        if (str_ends_with($name, $suffix)) {
            return $name;
        }

        return $name . $suffix;
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

    private function thankYouValidationRules(bool $isLegacy, bool $isUpdate): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template_type' => ['nullable', 'in:' . ThankYouPage::TEMPLATE_TYPE_LEGACY . ',' . ThankYouPage::TEMPLATE_TYPE_GEO_AWARE_V2],
            'title_text' => [$isLegacy ? 'required' : 'nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => [$isLegacy && !$isUpdate ? 'required' : 'nullable', 'image', 'max:5120'],
            'profile_image' => ['nullable', 'image', 'max:5120'],
            'hero_background_color' => ['required', 'string', 'max:50'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_profile_image' => ['nullable', 'boolean'],
            'v2_page_title' => ['nullable', 'string', 'max:255'],
            'v2_top_strip_text' => ['nullable', 'string', 'max:300'],
            'v2_banner_limited_text' => ['nullable', 'string', 'max:200'],
            'v2_banner_heading' => ['nullable', 'string', 'max:300'],
            'v2_banner_text_1' => ['nullable', 'string', 'max:1000'],
            'v2_banner_text_2' => ['nullable', 'string', 'max:1000'],
            'v2_call_scheduled_text' => ['nullable', 'string', 'max:300'],
            'v2_call_setup_text' => ['nullable', 'string', 'max:1000'],
            'v2_what_happens_heading' => ['nullable', 'string', 'max:255'],
            'v2_footer_text' => ['nullable', 'string', 'max:300'],
            'v2_registration_prefix' => ['nullable', 'string', 'max:300'],
            'v2_scheduled_label_today' => ['nullable', 'string', 'max:100'],
            'v2_scheduled_label_tomorrow' => ['nullable', 'string', 'max:100'],
            'v2_why_wait_heading' => ['nullable', 'string', 'max:255'],
            'v2_why_wait_text_1' => ['nullable', 'string', 'max:500'],
            'v2_why_wait_text_2' => ['nullable', 'string', 'max:1000'],
            'v2_why_wait_quote' => ['nullable', 'string', 'max:500'],
            'v2_customer_reviews_heading' => ['nullable', 'string', 'max:255'],
            'v2_reviews' => ['nullable', 'array'],
            'v2_reviews.items' => ['nullable', 'array'],
            'v2_reviews.items.*.quote' => ['nullable', 'string', 'max:1000'],
            'v2_reviews.items.*.author' => ['nullable', 'string', 'max:255'],
            'v2_what_happens_next' => ['nullable', 'array'],
            'v2_what_happens_next.heading' => ['nullable', 'string', 'max:255'],
            'v2_what_happens_next.steps' => ['nullable', 'array'],
            'v2_what_happens_next.steps.*.title' => ['nullable', 'string', 'max:255'],
            'v2_what_happens_next.steps.*.description' => ['nullable', 'string', 'max:1000'],
            'v2_footer' => ['nullable', 'array'],
            'v2_footer.copyright' => ['nullable', 'string', 'max:300'],
            'v2_geo_cutoff_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'v2_geo_skip_weekends' => ['nullable', 'boolean'],
            'v2_geo_sunday_cutoff_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'v2_geo_default_visitor_tz' => ['nullable', 'string', 'max:120'],
            'v2_geo_country_overrides_json' => ['nullable', 'string', 'max:20000'],
        ];
    }

    private function extractV2Content(array $validated): array
    {
        $keys = [
            'v2_page_title',
            'v2_top_strip_text',
            'v2_banner_limited_text',
            'v2_banner_heading',
            'v2_banner_text_1',
            'v2_banner_text_2',
            'v2_call_scheduled_text',
            'v2_call_setup_text',
            'v2_what_happens_heading',
            'v2_footer_text',
            'v2_registration_prefix',
            'v2_scheduled_label_today',
            'v2_scheduled_label_tomorrow',
            'v2_why_wait_heading',
            'v2_why_wait_text_1',
            'v2_why_wait_text_2',
            'v2_why_wait_quote',
            'v2_customer_reviews_heading',
            'v2_reviews',
            'v2_what_happens_next',
            'v2_footer',
            'v2_geo_cutoff_hour',
            'v2_geo_skip_weekends',
            'v2_geo_sunday_cutoff_hour',
            'v2_geo_default_visitor_tz',
            'v2_geo_country_overrides_json',
        ];

        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $validated)) {
                $result[$key] = trim((string) ($validated[$key] ?? ''));
            }
        }

        return $result;
    }

    private function renderGeoAwareV2Preview(ThankYouPage $page): string
    {
        $templatePath = public_path('thankyou_templates/geo_aware_v2/thankyou.php');
        if (!is_file($templatePath)) {
            return '<h1>Geo-aware v2 template file missing.</h1>';
        }
        $baseConfigPath = public_path('thankyou_templates/geo_aware_v2/config.php');
        $baseConfig = is_file($baseConfigPath) ? (require $baseConfigPath) : [];
        $v2 = $this->normalizeGeoAwareV2Content(is_array($page->v2_content) ? $page->v2_content : []);
        $GEO_CONFIG = $this->buildGeoConfigArrayFromV2($baseConfig, $v2);

        ob_start();
        try {
            include $templatePath;
            $html = (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            return '<h1>Preview failed to render.</h1>';
        }

        $html = $this->applyGeoAwareV2DynamicMappings($html, $v2, true);

        // Preview is served from /thank-you-pages/{id}/preview, so relative "./..." paths break.
        // Rewrite to absolute paths under Builder public for correct asset loading.
        $assetBase = rtrim(url('/thankyou_templates/geo_aware_v2'), '/');
        $html = str_replace('href="./', 'href="' . $assetBase . '/', $html);
        $html = str_replace('src="./', 'src="' . $assetBase . '/', $html);

        return $html;
    }

    private function buildGeoConfigArrayFromV2(array $baseConfig, array $v2): array
    {
        $config = is_array($baseConfig) ? $baseConfig : [];
        if (!isset($config['DEFAULT']) || !is_array($config['DEFAULT'])) {
            $config['DEFAULT'] = [
                'cutoff_hour' => 19,
                'skip_weekends' => true,
                'sunday_cutoff_hour' => 17,
                'visitor_tz' => 'UTC',
            ];
        }

        $default = $config['DEFAULT'];
        $cutoff = isset($v2['v2_geo_cutoff_hour']) && $v2['v2_geo_cutoff_hour'] !== '' ? (int) $v2['v2_geo_cutoff_hour'] : (int) ($default['cutoff_hour'] ?? 19);
        $skip = array_key_exists('v2_geo_skip_weekends', $v2) ? filter_var($v2['v2_geo_skip_weekends'], FILTER_VALIDATE_BOOLEAN) : (bool) ($default['skip_weekends'] ?? true);
        $sun = isset($v2['v2_geo_sunday_cutoff_hour']) && $v2['v2_geo_sunday_cutoff_hour'] !== '' ? (int) $v2['v2_geo_sunday_cutoff_hour'] : (int) ($default['sunday_cutoff_hour'] ?? 17);
        $tz = trim((string) ($v2['v2_geo_default_visitor_tz'] ?? ($default['visitor_tz'] ?? 'UTC')));
        if ($tz === '') {
            $tz = 'UTC';
        }

        $config['DEFAULT'] = [
            'cutoff_hour' => max(0, min(23, $cutoff)),
            'skip_weekends' => (bool) $skip,
            'sunday_cutoff_hour' => max(0, min(23, $sun)),
            'visitor_tz' => $tz,
        ];

        $rawOverrides = trim((string) ($v2['v2_geo_country_overrides_json'] ?? ''));
        if ($rawOverrides !== '') {
            $decoded = json_decode($rawOverrides, true);
            if (is_array($decoded)) {
                foreach ($decoded as $country => $row) {
                    $cc = strtoupper(trim((string) $country));
                    if (!preg_match('/^[A-Z]{2}$/', $cc) || !is_array($row)) {
                        continue;
                    }
                    $baseRow = isset($config[$cc]) && is_array($config[$cc]) ? $config[$cc] : $config['DEFAULT'];
                    $config[$cc] = [
                        'cutoff_hour' => isset($row['cutoff_hour']) ? max(0, min(23, (int) $row['cutoff_hour'])) : (int) ($baseRow['cutoff_hour'] ?? $config['DEFAULT']['cutoff_hour']),
                        'skip_weekends' => array_key_exists('skip_weekends', $row) ? (bool) $row['skip_weekends'] : (bool) ($baseRow['skip_weekends'] ?? $config['DEFAULT']['skip_weekends']),
                        'sunday_cutoff_hour' => isset($row['sunday_cutoff_hour']) ? max(0, min(23, (int) $row['sunday_cutoff_hour'])) : (int) ($baseRow['sunday_cutoff_hour'] ?? $config['DEFAULT']['sunday_cutoff_hour']),
                        'visitor_tz' => trim((string) ($row['visitor_tz'] ?? ($baseRow['visitor_tz'] ?? $config['DEFAULT']['visitor_tz']))),
                    ];
                    if ($config[$cc]['visitor_tz'] === '') {
                        $config[$cc]['visitor_tz'] = $config['DEFAULT']['visitor_tz'];
                    }
                }
            }
        }

        return $config;
    }

    private function applyGeoAwareV2DynamicMappings(string $html, array $v2, bool $previewMode): string
    {
        $escape = fn (?string $v, string $d = ''): string => htmlspecialchars(trim((string) ($v ?? '')) !== '' ? trim((string) $v) : $d, ENT_QUOTES, 'UTF-8');

        $bannerText1 = trim((string) ($v2['v2_banner_text_1'] ?? ''));
        if ($bannerText1 === '') {
            $bannerText1 = 'Your request has been received. A licensed broker will contact you {{call_phrase}} to guide you through setting up your AI trading account.';
        }
        $callSetupText = trim((string) ($v2['v2_call_setup_text'] ?? ''));
        if ($callSetupText === '') {
            $callSetupText = 'Your concierge will call {{call_phrase}} to set up your trading account.';
        }
        if ($previewMode) {
            $bannerText1 = str_replace('{{call_phrase}}', 'today', $bannerText1);
            $callSetupText = str_replace('{{call_phrase}}', 'today', $callSetupText);
        } else {
            $bannerText1 = str_replace('{{call_phrase}}', '<?= htmlspecialchars($call_phrase, ENT_QUOTES, \'UTF-8\') ?>', $bannerText1);
            $callSetupText = str_replace('{{call_phrase}}', '<?= htmlspecialchars($call_phrase, ENT_QUOTES, \'UTF-8\') ?>', $callSetupText);
        }

        $html = preg_replace('/<title>.*?<\/title>/si', '<title>' . $escape($v2['v2_page_title'] ?? null, 'AI - Thank You') . '</title>', $html);
        $html = preg_replace('/(<p class="top-strip-text">)(.*?)(<img)/si', '$1' . $escape($v2['v2_top_strip_text'] ?? null, 'Application Approved :: Access Unlocked') . '$3', $html);
        $html = preg_replace('/(<p class="banner-lmt-text">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_banner_limited_text'] ?? null, 'Limited Spots Available') . '$3', $html);
        $html = preg_replace('/(<p class="banner-heading">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_banner_heading'] ?? null, "You're On The List.") . '$3', $html);
        $html = preg_replace('/(<p class="banner-text1">)(.*?)(<\/p>)/si', '$1' . $bannerText1 . '$3', $html);
        $html = preg_replace('/(<p class="banner-text2">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_banner_text_2'] ?? null, 'We onboard a limited number of users every day to ensure one-on-one support.') . '$3', $html);
        $html = preg_replace('/(<p class="s1-call-row-text">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_call_scheduled_text'] ?? null, 'Your Call Has Been Scheduled') . '$3', $html);
        $html = preg_replace('/(<p class="s1-call-setup-text" id="call-text">)(.*?)(<\/p>)/si', '$1' . $callSetupText . '$3', $html);
        $what = $this->normalizeWhatHappensNextPayload($v2['v2_what_happens_next'] ?? null);
        $html = preg_replace('/(<p class="common-heading text-left-mob">)(.*?)(<\/p>)/si', '$1' . $escape($what['heading'] ?? null, 'What Happens Next') . '$3', $html);
        foreach ($what['steps'] as $idx => $step) {
            $stepNum = $idx + 1;
            $title = $escape($step['title'] ?? '', '');
            $desc = (string) ($step['description'] ?? '');
            $desc = $previewMode
                ? str_replace('{{call_phrase}}', 'today', $desc)
                : str_replace('{{call_phrase}}', '<?= htmlspecialchars($call_phrase, ENT_QUOTES, \'UTF-8\') ?>', $desc);

            $titlePattern = '/(<div class="s1-step-col">\s*<div class="s1-step-col-num">' . $stepNum . '<\/div>\s*<div class="s1-step-col-cont">\s*<h3>)(.*?)(<\/h3>)/si';
            $descPattern = '/(<div class="s1-step-col">\s*<div class="s1-step-col-num">' . $stepNum . '<\/div>\s*<div class="s1-step-col-cont">\s*<h3>.*?<\/h3>\s*<p>)(.*?)(<\/p>)/si';
            $html = preg_replace($titlePattern, '$1' . $title . '$3', $html, 1);
            $html = preg_replace($descPattern, '$1' . $desc . '$3', $html, 1);
        }
        $footer = $this->normalizeFooterPayload($v2['v2_footer'] ?? null);
        $html = preg_replace('/(<p class="footer-txt1">\s*)(.*?)(\s*<\/p>)/si', '$1' . $escape($footer['copyright'] ?? null, '2026 (c) All Rights Reserved.') . '$3', $html);
        $html = preg_replace('/(<p class="s1-register-text">\s*)(PLEASE REFERENCE YOUR REGISTRATION DATE AS:)(\s*<span>)/i', '$1' . $escape($v2['v2_registration_prefix'] ?? null, 'PLEASE REFERENCE YOUR REGISTRATION DATE AS:') . '$3', $html);

        $html = preg_replace('/(<div class="section2">.*?<p class="common-heading">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_why_wait_heading'] ?? null, 'Why the wait?') . '$3', $html, 1);
        $html = preg_replace('/(<div class="section2">.*?<p class="common-text text-left-mob">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_why_wait_text_1'] ?? null, "This isn't a mass-market product.") . '$3', $html, 1);
        $html = preg_replace('/(<div class="section2">.*?<p class="common-text text-left-mob">.*?<\/p>\s*<p class="common-text text-left-mob">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_why_wait_text_2'] ?? null, 'We limit new user onboarding daily to ensure every customer receives personalized, broker-led guidance.') . '$3', $html, 1);
        $html = preg_replace('/(<div class="section2">.*?<p class="common-text text-left-mob"><em><strong>)(.*?)(<\/strong><\/em><\/p>)/si', '$1' . $escape($v2['v2_why_wait_quote'] ?? null, "\"We don't rush onboarding - we do it right.\"") . '$3', $html, 1);

        $html = preg_replace('/(<div class="section3">.*?<p class="common-heading">)(.*?)(<\/p>)/si', '$1' . $escape($v2['v2_customer_reviews_heading'] ?? null, 'Customer Reviews') . '$3', $html, 1);
        $reviews = $this->normalizeReviewsPayload($v2['v2_reviews'] ?? null);
        foreach ($reviews['items'] as $idx => $item) {
            $n = $idx + 1;
            $quotePattern = '/(<div class="s3-col">.*?<p class="s3-col-text1">)(.*?)(<\/p>)/si';
            $authorPattern = '/(<div class="s3-col">.*?<p class="s3-col-text1">.*?<\/p>\s*<p class="s3-col-text2">)(.*?)(<\/p>)/si';
            $quote = $escape($item['quote'] ?? '', '');
            $author = $escape($item['author'] ?? '', '');
            $qi = 0;
            $html = preg_replace_callback($quotePattern, function ($m) use (&$qi, $n, $quote) {
                $qi++;
                return $qi === $n ? $m[1] . $quote . $m[3] : $m[0];
            }, $html, 1);
            $ai = 0;
            $html = preg_replace_callback($authorPattern, function ($m) use (&$ai, $n, $author) {
                $ai++;
                return $ai === $n ? $m[1] . $author . $m[3] : $m[0];
            }, $html, 1);
        }

        if (preg_match('/<p class="s1-scheduled-text" id="scheduled-label">(.*?)<\/p>/si', $html, $m)) {
            $current = strtolower(trim(strip_tags($m[1])));
            $replacement = $current === 'tomorrow'
                ? $escape($v2['v2_scheduled_label_tomorrow'] ?? null, 'TOMORROW')
                : $escape($v2['v2_scheduled_label_today'] ?? null, 'TODAY');
            $html = preg_replace('/(<p class="s1-scheduled-text" id="scheduled-label">)(.*?)(<\/p>)/si', '$1' . $replacement . '$3', $html);
        }

        return $html;
    }

    private function geoAwareV2DefaultContent(): array
    {
        return [
            'v2_page_title' => 'AI - Thank You',
            'v2_top_strip_text' => 'Application Approved :: Access Unlocked',
            'v2_banner_limited_text' => 'Limited Spots Available',
            'v2_banner_heading' => "You're On The List.",
            'v2_banner_text_1' => 'Your request has been received. A licensed broker will contact you {{call_phrase}} to guide you through setting up your AI trading account.',
            'v2_banner_text_2' => 'We onboard a limited number of users every day to ensure one-on-one support.',
            'v2_call_scheduled_text' => 'Your Call Has Been Scheduled',
            'v2_call_setup_text' => 'Your concierge will call {{call_phrase}} to set up your trading account.',
            'v2_what_happens_heading' => 'What Happens Next',
            'v2_footer_text' => '2026 (c) All Rights Reserved.',
            'v2_what_happens_next' => [
                'heading' => 'What Happens Next',
                'steps' => [
                    [
                        'title' => "You're in the queue.",
                        'description' => 'Expect a call from your assigned platform broker {{call_phrase}}.',
                    ],
                    [
                        'title' => 'Set up and fund your account',
                        'description' => 'Your broker will guide you through selecting the right AI platform and funding it.',
                    ],
                    [
                        'title' => 'Start earning automated income',
                        'description' => 'Once your account is live, your AI trading system will begin executing trades automatically.',
                    ],
                ],
            ],
            'v2_footer' => [
                'copyright' => '2026 (c) All Rights Reserved.',
            ],
            'v2_registration_prefix' => 'PLEASE REFERENCE YOUR REGISTRATION DATE AS:',
            'v2_scheduled_label_today' => 'TODAY',
            'v2_scheduled_label_tomorrow' => 'TOMORROW',
            'v2_why_wait_heading' => 'Why the wait?',
            'v2_why_wait_text_1' => "This isn't a mass-market product.",
            'v2_why_wait_text_2' => 'We limit new user onboarding daily to ensure every customer receives personalized, broker-led guidance.',
            'v2_why_wait_quote' => "\"We don't rush onboarding - we do it right.\"",
            'v2_customer_reviews_heading' => 'Customer Reviews',
            'v2_reviews' => [
                'items' => [
                    ['quote' => '"The broker explained everything clearly. I felt confident and supported."', 'author' => '- A.S., Paris'],
                    ['quote' => '"Wasn\'t sure what to expect, but within a day I was set up and seeing trades."', 'author' => '- P.R., London'],
                    ['quote' => '"Very professional, no pressure. My account was live right after the call."', 'author' => '- D.T., Munich'],
                ],
            ],
            'v2_geo_cutoff_hour' => 19,
            'v2_geo_skip_weekends' => true,
            'v2_geo_sunday_cutoff_hour' => 17,
            'v2_geo_default_visitor_tz' => 'UTC',
            'v2_geo_country_overrides_json' => '',
        ];
    }

    private function normalizeGeoAwareV2Content(array $content): array
    {
        $defaults = $this->geoAwareV2DefaultContent();
        $normalized = $defaults;

        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $content)) {
                continue;
            }
            $value = $content[$key];
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }
                $normalized[$key] = $trimmed;
                continue;
            }
            if ($value === null) {
                continue;
            }
            $normalized[$key] = $value;
        }

        $normalized['v2_what_happens_next'] = $this->normalizeWhatHappensNextPayload($content['v2_what_happens_next'] ?? ($content['v2_what_happens_heading'] ?? null));
        $normalized['v2_what_happens_heading'] = $normalized['v2_what_happens_next']['heading'];
        $normalized['v2_footer'] = $this->normalizeFooterPayload($content['v2_footer'] ?? ($content['v2_footer_text'] ?? null));
        $normalized['v2_footer_text'] = $normalized['v2_footer']['copyright'];
        $normalized['v2_reviews'] = $this->normalizeReviewsPayload($content['v2_reviews'] ?? null);

        return $normalized;
    }

    private function normalizeReviewsPayload(mixed $payload): array
    {
        $default = $this->geoAwareV2DefaultContent()['v2_reviews'];
        if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
            return $default;
        }
        $items = $default['items'];
        foreach ([0, 1, 2] as $idx) {
            if (!isset($payload['items'][$idx]) || !is_array($payload['items'][$idx])) {
                continue;
            }
            $q = trim((string) ($payload['items'][$idx]['quote'] ?? ''));
            $a = trim((string) ($payload['items'][$idx]['author'] ?? ''));
            if ($q !== '') {
                $items[$idx]['quote'] = $q;
            }
            if ($a !== '') {
                $items[$idx]['author'] = $a;
            }
        }
        return ['items' => $items];
    }

    private function normalizeWhatHappensNextPayload(mixed $payload): array
    {
        $default = $this->geoAwareV2DefaultContent()['v2_what_happens_next'];
        if (is_string($payload)) {
            $payload = ['heading' => trim($payload)];
        }
        if (!is_array($payload)) {
            return $default;
        }

        $heading = trim((string) ($payload['heading'] ?? ''));
        if ($heading === '') {
            $heading = $default['heading'];
        }

        $steps = $default['steps'];
        if (isset($payload['steps']) && is_array($payload['steps'])) {
            foreach ([0, 1, 2] as $idx) {
                if (!isset($payload['steps'][$idx]) || !is_array($payload['steps'][$idx])) {
                    continue;
                }
                $title = trim((string) ($payload['steps'][$idx]['title'] ?? ''));
                $description = trim((string) ($payload['steps'][$idx]['description'] ?? ''));
                if ($title !== '') {
                    $steps[$idx]['title'] = $title;
                }
                if ($description !== '') {
                    $steps[$idx]['description'] = $description;
                }
            }
        }

        return [
            'heading' => $heading,
            'steps' => $steps,
        ];
    }

    private function normalizeFooterPayload(mixed $payload): array
    {
        $default = $this->geoAwareV2DefaultContent()['v2_footer'];
        if (is_string($payload)) {
            $payload = ['copyright' => trim($payload)];
        }
        if (!is_array($payload)) {
            return $default;
        }

        $copyright = trim((string) ($payload['copyright'] ?? ''));
        if ($copyright === '') {
            $copyright = $default['copyright'];
        }

        return [
            'copyright' => $copyright,
        ];
    }
}

