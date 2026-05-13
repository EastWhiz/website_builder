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
}
