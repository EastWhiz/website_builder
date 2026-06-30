<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnstileWidget extends Model
{
    protected $fillable = [
        'organization_id',
        'hostname',
        'cloudflare_widget_id',
        'site_key',
        'secret_key_encrypted',
        'mode',
        'widget_scope',
        'domains_json',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'secret_key_encrypted' => 'encrypted',
            'domains_json' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, ?int $organizationId)
    {
        if (!$organizationId) {
            return $query;
        }

        return $query->where('organization_id', $organizationId);
    }
}
