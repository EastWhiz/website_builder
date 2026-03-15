<?php

namespace App\Models;

use App\Services\ThankYouPageImageService;
use Illuminate\Database\Eloquent\Model;

class ThankYouPage extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'logo_path',
        'title_text',
        'description',
        'profile_image_path',
        'hero_background_color',
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
