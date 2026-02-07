<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class OtpServiceCredential extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'credentials', // JSON column
    ];

    protected $casts = [
        'credentials' => 'array', // Auto JSON decode
    ];

    /**
     * Get the service that this credential belongs to
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(OtpService::class, 'service_id');
    }

    /**
     * Get the user that owns the OTP service credential
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Accessor: Decrypt credentials when accessed
     * Returns decrypted credentials based on field definitions
     */
    public function getDecryptedCredentialsAttribute()
    {
        if (!$this->credentials) {
            return [];
        }

        $decrypted = [];
        $serviceFields = $this->service->fields ?? [];
        
        foreach ($this->credentials as $key => $value) {
            // Find field definition to check if it should be encrypted
            $fieldDef = collect($serviceFields)->firstWhere('name', $key);
            
            if ($fieldDef && ($fieldDef['encrypt'] ?? false) && !empty($value)) {
                try {
                    $decrypted[$key] = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    // If decryption fails, return as-is (might be plain text from old data)
                    $decrypted[$key] = $value;
                }
            } else {
                $decrypted[$key] = $value;
            }
        }
        
        return $decrypted;
    }

    /**
     * Mutator: Encrypt credentials before saving
     * Encrypts fields marked with encrypt: true in service definition
     */
    public function setCredentialsAttribute($value)
    {
        if (!is_array($value)) {
            $this->attributes['credentials'] = json_encode([]);
            return;
        }

        $encrypted = [];
        $serviceFields = [];

        // Get service fields definition
        if ($this->service_id) {
            // Try to load service if not already loaded
            if (!$this->relationLoaded('service')) {
                $service = OtpService::find($this->service_id);
                if ($service) {
                    $serviceFields = $service->fields ?? [];
                }
            } else {
                $serviceFields = $this->service->fields ?? [];
            }
        }
        
        foreach ($value as $key => $val) {
            if (empty($val) && $val !== '0') {
                $encrypted[$key] = $val;
                continue;
            }
            
            // Find field definition to check if it should be encrypted
            $fieldDef = collect($serviceFields)->firstWhere('name', $key);
            
            if ($fieldDef && ($fieldDef['encrypt'] ?? false)) {
                try {
                    // Check if already encrypted (starts with eyJ for Laravel encrypted strings)
                    if (is_string($val) && strpos($val, 'eyJ') === 0) {
                        // Might already be encrypted, try to decrypt first
                        try {
                            Crypt::decryptString($val);
                            // If decryption succeeds, it's already encrypted, keep as is
                            $encrypted[$key] = $val;
                        } catch (\Exception $e) {
                            // Not encrypted, encrypt it
                            $encrypted[$key] = Crypt::encryptString($val);
                        }
                    } else {
                        $encrypted[$key] = Crypt::encryptString($val);
                    }
                } catch (\Exception $e) {
                    // If encryption fails, store as plain text (shouldn't happen)
                    $encrypted[$key] = $val;
                }
            } else {
                $encrypted[$key] = $val;
            }
        }
        
        $this->attributes['credentials'] = json_encode($encrypted);
    }
}
