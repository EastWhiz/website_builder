<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'status',
        'primary_user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot(['role_id', 'status', 'invited_at', 'accepted_at', 'deleted_at'])
            ->withTimestamps();
    }

    public function angles(): HasMany
    {
        return $this->hasMany(Angle::class);
    }

    public function angleTemplates(): HasMany
    {
        return $this->hasMany(AngleTemplate::class);
    }

    public function thankYouPages(): HasMany
    {
        return $this->hasMany(ThankYouPage::class);
    }

    public function apiInstances(): HasMany
    {
        return $this->hasMany(UserApiInstance::class);
    }
}

