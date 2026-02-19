<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiCategoryField extends Model
{
    protected $fillable = [
        'api_category_id',
        'name',
        'label',
        'type',
        'placeholder',
        'is_required',
        'encrypt',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'encrypt' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ApiCategory::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(UserApiInstanceValue::class);
    }
}
