<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiCredential extends Model
{
    protected $fillable = [
        'user_id',
        'aweber_client_id',
        'aweber_client_secret',
        'aweber_account_id',
        'aweber_list_id',
        'electra_affid',
        'electra_api_key',
        'dark_username',
        'dark_password',
        'dark_api_key',
        'dark_ai',
        'dark_ci',
        'dark_gi',
        'elps_username',
        'elps_password',
        'elps_api_key',
        'elps_ai',
        'elps_ci',
        'elps_gi',
        'meeseeks_api_key',
        'novelix_api_key',
        'novelix_affid',
        'tigloo_username',
        'tigloo_password',
        'tigloo_api_key',
        'tigloo_ai',
        'tigloo_ci',
        'tigloo_gi',
    ];

    // protected $casts = [
    //     'aweber_client_id' => 'encrypted',
    //     'aweber_client_secret' => 'encrypted',
    //     'electra_api_key' => 'encrypted',
    //     'dark_password' => 'encrypted',
    //     'dark_api_key' => 'encrypted',
    //     'elps_password' => 'encrypted',
    //     'elps_api_key' => 'encrypted',
    //     'meeseeks_api_key' => 'encrypted',
    //     'novelix_api_key' => 'encrypted',
    //     'tigloo_password' => 'encrypted',
    //     'tigloo_api_key' => 'encrypted',
    // ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
