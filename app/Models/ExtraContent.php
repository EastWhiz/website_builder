<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraContent extends Model
{
    protected $fillable = [
        'angle_template_uuid',
        'angle_content_uuid',
        'angle_uuid',
        'asset_unique_uuid',
        'name',
        'blob_url',
        'type',
        'can_be_deleted',
    ];

    public function angleContent()
    {
        return $this->belongsTo(AngleContent::class, 'angle_content_uuid', 'uuid');
    }
}
