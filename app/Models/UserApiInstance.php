<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserApiInstance extends Model
{
    protected $fillable = [
        'user_id',
        'api_category_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ApiCategory::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(UserApiInstanceValue::class);
    }

    public function getCredentialsAttribute(): array
    {
        $credentials = [];
        foreach ($this->values as $value) {
            if (!$value->field) {
                continue;
            }
            $credentials[$value->field->name] = $value->decrypted_value;
        }
        return $credentials;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
