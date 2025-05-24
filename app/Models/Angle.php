<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Angle extends Model
{
    protected $fillable = [
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
}
