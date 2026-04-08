<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Angle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'uuid',
        'asset_unique_uuid',
    ];

    public function contents()
    {
        return $this->hasMany(AngleContent::class, 'angle_uuid', 'uuid');
    }

    public function angleTemplates()
    {
        return $this->hasMany(AngleTemplate::class, 'angle_id', 'id');
    }

    public function extraContents()
    {
        return $this->hasMany(extraContent::class, 'angle_uuid', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
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
}
