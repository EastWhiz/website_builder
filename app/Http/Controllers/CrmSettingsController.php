<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmSettingsController extends Controller
{
    public const KEY_MODE = 'crm_mode';

    public const KEY_URL_PRODUCTION = 'crm_url_production';

    public const KEY_URL_DEV = 'crm_url_dev';

    public const MODE_PRODUCTION = 'production';

    public const MODE_DEV = 'dev';

    private function isAdmin(): bool
    {
        $user = Auth::user();

        return $user && $user->email === 'admin@gmail.com';
    }

    /**
     * Get CRM Server settings (admin only).
     */
    public function index(Request $request)
    {
        if (! $this->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'crm_mode' => Setting::get(self::KEY_MODE, self::MODE_PRODUCTION),
            'crm_url_production' => Setting::get(self::KEY_URL_PRODUCTION, 'https://crm.diy'),
            'crm_url_dev' => Setting::get(self::KEY_URL_DEV, ''),
        ]);
    }

    /**
     * Update CRM settings (admin only).
     */
    public function update(Request $request)
    {
        if (! $this->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'crm_mode' => 'required|in:production,dev',
            'crm_url_production' => 'nullable|string|max:500',
            'crm_url_dev' => 'nullable|string|max:500',
        ]);

        Setting::set(self::KEY_MODE, $validated['crm_mode']);
        Setting::set(self::KEY_URL_PRODUCTION, $validated['crm_url_production'] ?? '');
        Setting::set(self::KEY_URL_DEV, $validated['crm_url_dev'] ?? '');

        return response()->json([
            'message' => 'CRM settings saved.',
            'crm_mode' => Setting::get(self::KEY_MODE),
            'crm_url_production' => Setting::get(self::KEY_URL_PRODUCTION),
            'crm_url_dev' => Setting::get(self::KEY_URL_DEV),
        ]);
    }
}
