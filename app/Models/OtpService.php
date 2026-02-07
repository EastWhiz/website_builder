<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtpService extends Model
{
    protected $fillable = [
        'name',
        'fields',
        'is_active',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all user credentials for this service
     */
    public function userCredentials(): HasMany
    {
        return $this->hasMany(OtpServiceCredential::class, 'service_id');
    }
}
