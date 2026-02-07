<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpServiceCredential extends Model
{
    protected $fillable = [
        'user_id',
        'service_name',
        'access_key',
        'endpoint_url',
    ];

    /**
     * Get the user that owns the OTP service credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
