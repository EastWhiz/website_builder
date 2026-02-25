<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserApiInstanceValue extends Model
{
    protected $fillable = [
        'user_api_instance_id',
        'api_category_field_id',
        'value',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(UserApiInstance::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ApiCategoryField::class, 'api_category_field_id');
    }

    public function getDecryptedValueAttribute(): string
    {
        if (!$this->value) {
            return '';
        }

        // Decrypt if field is marked for encryption
        if ($this->field && $this->field->encrypt) {
            try {
                return Crypt::decryptString($this->value);
            } catch (\Exception $e) {
                // If decryption fails, return as-is (might be plain text)
                return $this->value;
            }
        }

        return $this->value;
    }

    public function setValueAttribute($value): void
    {
        // Load field if not already loaded
        if (!$this->relationLoaded('field') && $this->api_category_field_id) {
            $this->load('field');
        }

        // Encrypt if field is marked for encryption
        if ($this->field && $this->field->encrypt && !empty($value)) {
            try {
                // Check if already encrypted
                try {
                    Crypt::decryptString($value);
                    // Already encrypted, store as-is
                    $this->attributes['value'] = $value;
                } catch (\Exception $e) {
                    // Not encrypted, encrypt it
                    $this->attributes['value'] = Crypt::encryptString($value);
                }
            } catch (\Exception $e) {
                // If encryption fails, store as plain text
                $this->attributes['value'] = $value;
            }
        } else {
            $this->attributes['value'] = $value;
        }
    }
}
