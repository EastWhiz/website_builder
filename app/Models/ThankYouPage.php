<?php

namespace App\Models;

use App\Services\ThankYouPageImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThankYouPage extends Model
{
    use SoftDeletes;

    public const TEMPLATE_TYPE_LEGACY = 'legacy';
    public const TEMPLATE_TYPE_GEO_AWARE_V2 = 'geo_aware_v2';

    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'logo_path',
        'title_text',
        'description',
        'profile_image_path',
        'hero_background_color',
        'template_type',
        'v2_content',
        'facebook_pixel_url',
        'second_pixel_url',
    ];

    protected $casts = [
        'v2_content' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ThankYouPage $page) {
            app(ThankYouPageImageService::class)->deleteImagesForPage($page);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, ?int $organizationId)
    {
        if (!$organizationId) {
            return $query;
        }

        return $query->where('organization_id', $organizationId);
    }

    /**
     * Full URL for logo (for preview / display).
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }
        return asset(ltrim($this->logo_path, '/'));
    }

    /**
     * Full URL for profile image (for preview / display).
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        if (empty($this->profile_image_path)) {
            return null;
        }
        return asset(ltrim($this->profile_image_path, '/'));
    }

    /**
     * Copy pixel URLs from the user's API credentials (Profile → Pixel Management).
     *
     * @return array{facebook_pixel_url: ?string, second_pixel_url: ?string}
     */
    public static function pixelUrlsFromUser(?User $user): array
    {
        if (!$user) {
            return ['facebook_pixel_url' => null, 'second_pixel_url' => null];
        }

        $credentials = $user->apiCredential;
        $facebook = trim((string) ($credentials?->facebook_pixel_url ?? ''));
        $second = trim((string) ($credentials?->second_pixel_url ?? ''));

        return [
            'facebook_pixel_url' => $facebook !== '' ? $facebook : null,
            'second_pixel_url' => $second !== '' ? $second : null,
        ];
    }

    /**
     * Inject Facebook/Voluum pixel URLs into exported thank_you.php content.
     * Page-level URLs take precedence; falls back to export-time user credentials.
     */
    public static function injectPixelUrlsIntoThankYouContent(
        string $content,
        ?self $page,
        ?UserApiCredential $credentials
    ): string {
        $facebook = trim((string) ($page?->facebook_pixel_url ?? ''));
        if ($facebook === '') {
            $facebook = trim((string) ($credentials?->facebook_pixel_url ?? ''));
        }

        $second = trim((string) ($page?->second_pixel_url ?? ''));
        if ($second === '') {
            $second = trim((string) ($credentials?->second_pixel_url ?? ''));
        }

        $content = str_replace(
            "let DynamicFacebookPixelURL = '';",
            "let DynamicFacebookPixelURL = '" . $facebook . "';",
            $content
        );
        $content = str_replace(
            "let DynamicSecondaryPixelURL = '';",
            "let DynamicSecondaryPixelURL = '" . $second . "';",
            $content
        );

        return $content;
    }
}
