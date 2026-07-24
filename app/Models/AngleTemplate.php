<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AngleTemplate extends Model
{
    use SoftDeletes;

    public const CONTENT_MODE_LEGACY = 'legacy';

    public const CONTENT_MODE_STRUCTURED_BD = 'structured_bd';

    protected $fillable = [
        'uuid',
        'angle_id',
        'template_id',
        'user_id',
        'organization_id',
        'name',
        'main_html',
        'main_css',
        'main_js',
        'content_mode',
        'structured_version',
    ];

    protected $casts = [
        'structured_version' => 'integer',
    ];

    public function angle()
    {
        return $this->belongsTo(Angle::class, 'angle_id');
    }

    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function contents()
    {
        return $this->hasMany(ExtraContent::class, 'angle_template_uuid', 'uuid');
    }

    public function bdContents()
    {
        return $this->hasMany(AngleTemplateBdContent::class, 'angle_template_id');
    }

    public function isStructuredBd(): bool
    {
        return $this->content_mode === self::CONTENT_MODE_STRUCTURED_BD;
    }

    public function isLegacyContent(): bool
    {
        return ! $this->isStructuredBd();
    }

    public function scopeForOrganization($query, ?int $organizationId)
    {
        if (!$organizationId) {
            return $query;
        }

        return $query->where('organization_id', $organizationId);
    }
}
