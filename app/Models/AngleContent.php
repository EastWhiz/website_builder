<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AngleContent extends Model
{
    protected $fillable = [
        'angle_uuid',
        'name',
        'type',
        'content',
        'sort',
        'can_be_deleted',
    ];
}
