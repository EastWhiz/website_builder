<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserApiInstance extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        // Only hard-delete related values when the instance is force-deleted (permanent delete)
        static::forceDeleting(function (UserApiInstance $instance) {
            $instance->values()->delete();
        });
    }

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
        return $this->belongsTo(ApiCategory::class, 'api_category_id');
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
