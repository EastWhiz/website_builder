<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// class User extends Authenticatable implements MustVerifyEmail
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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

    public function angleTemplates()
    {
        return $this->hasMany(AngleTemplate::class, 'user_id');
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
}
