<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationTurnstileSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'enabled',
        'auto_provision_enabled',
        'cloudflare_account_id',
        'cloudflare_api_token_encrypted',
        'default_widget_mode',
        'widget_scope',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'auto_provision_enabled' => 'boolean',
            'cloudflare_api_token_encrypted' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
