<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

// class User extends Authenticatable implements MustVerifyEmail
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'deepl_api_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deepl_api_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->withPivot(['role_id', 'status', 'invited_at', 'accepted_at', 'deleted_at'])
            ->withTimestamps();
    }

    /**
     * Current organization helper for single-org assumption in Phase 1.
     */
    public function currentOrganization(): ?Organization
    {
        return $this->organizations()->wherePivot('status', 'active')->first();
    }

    public function angleTemplates()
    {
        return $this->hasMany(AngleTemplate::class, 'user_id');
    }

    public function thankYouPages()
    {
        return $this->hasMany(ThankYouPage::class, 'user_id');
    }

    public function apiCredential()
    {
        return $this->hasOne(UserApiCredential::class);
    }

    public function apiInstances()
    {
        return $this->hasMany(UserApiInstance::class);
    }

    public function activeApiInstances()
    {
        return $this->hasMany(UserApiInstance::class)->where('is_active', true);
    }

    public function getApiInstanceByCategory($categoryId)
    {
        return $this->apiInstances()
            ->where('api_category_id', $categoryId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the user's API instance that matches the given form_type (e.g. "elps" → instance named "Elps").
     * Used so existing landing pages keep the correct API selected after the new scenario.
     *
     * @param int $categoryId
     * @param string $formType e.g. "elps", "magicads", "meeseeksmedia"
     * @param array<string, string> $formTypeToCanonicalName e.g. ['meeseeksmedia' => 'meeseeks'] for name matching
     * @return \App\Models\UserApiInstance|null
     */
    public function getApiInstanceByFormType($categoryId, $formType, array $formTypeToCanonicalName = [])
    {
        $canonical = $formTypeToCanonicalName[$formType] ?? $formType;
        $canonicalLower = strtolower($canonical);

        $instance = $this->apiInstances()
            ->where('api_category_id', $categoryId)
            ->where('is_active', true)
            ->get()
            ->first(fn ($inst) => strtolower($inst->name) === $canonicalLower);

        return $instance ?? $this->getApiInstanceByCategory($categoryId);
    }

    /**
     * DeepL API key from the database (Profile). Translation is only allowed when this is set.
     */
    public function getDeeplApiKey(): string
    {
        return trim((string) ($this->deepl_api_key ?? ''));
    }
}
