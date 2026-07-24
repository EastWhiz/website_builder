<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AngleTemplateBdContent extends Model
{
    protected $fillable = [
        'angle_template_id',
        'angle_template_uuid',
        'parent_bd',
        'slot_key',
        'slot_type',
        'content',
        'sort',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort' => 'integer',
    ];

    public function angleTemplate()
    {
        return $this->belongsTo(AngleTemplate::class, 'angle_template_id');
    }
}
