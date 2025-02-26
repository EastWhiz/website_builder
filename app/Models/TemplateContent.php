<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateContent extends Model
{
    protected $fillable = [
        'template_uuid',
        'name',
        'type',
        'content',
        'sort',
    ];
}
