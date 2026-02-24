<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiCategory extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(ApiCategoryField::class)->orderBy('id');
    }

    public function userInstances(): HasMany
    {
        return $this->hasMany(UserApiInstance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
