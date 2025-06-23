<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AngleContent extends Model
{
    protected $fillable = [
        'uuid',
        'angle_uuid',
        'name',
        'type',
        'content',
        'sort',
        'can_be_deleted',
    ];

    // public function contents()
    // {
    //     return $this->hasMany(ExtraContent::class, 'angle_content_uuid', 'uuid');
    // }
}
