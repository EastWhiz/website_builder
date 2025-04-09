<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $fillable = [
        'name',
        'uuid',
        'asset_unique_uuid',
        'head',
        'index',
    ];

    public function contents()
    {
        return $this->hasMany(TemplateContent::class, 'template_uuid', 'uuid');
    }
}
