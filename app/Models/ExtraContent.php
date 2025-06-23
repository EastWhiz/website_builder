<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraContent extends Model
{
    protected $fillable = [
        'angle_template_uuid',
        'angle_content_uuid',
        'name',
        'blob_url',
        'type',
        'can_be_deleted',
    ];
}
