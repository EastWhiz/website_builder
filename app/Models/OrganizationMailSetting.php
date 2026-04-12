<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMailSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'mail_from_address',
        'mail_from_name',
    ];

    protected function casts(): array
    {
        return [
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
