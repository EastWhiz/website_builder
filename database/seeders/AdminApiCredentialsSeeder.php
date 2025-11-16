<?php

namespace Database\Seeders;

use App\Models\UserApiCredential;
use Illuminate\Database\Seeder;

class AdminApiCredentialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create API credentials for admin user (user_id = 1)
        UserApiCredential::updateOrCreate(
            ['user_id' => 1],
            [
                // AWeber API credentials
                'aweber_client_id' => 'lvrj2RItD1E5CE5YGUyq6akFhehKrvzC',
                'aweber_client_secret' => 'aJ5ji1uZKkCFpGoeEeuNPRPMGDTGLf3y',
                'aweber_account_id' => '2342136',
                'aweber_list_id' => '6858148',

                // Electra API credentials
                'electra_affid' => '13',
                'electra_api_key' => null, // Optional field

                // Dark API credentials
                'dark_username' => 'cfff',
                'dark_password' => '1YAnplgj!',
                'dark_api_key' => '2643889w34df345676ssdas323tgc738',
                'dark_ai' => '2958198',
                'dark_ci' => '1',
                'dark_gi' => '173',

                // ELPS API credentials
                'elps_username' => 'cfff',
                'elps_password' => '1YAnplgj!',
                'elps_api_key' => '2643889w34df345676ssdas323tgc738',
                'elps_ai' => '2958034',
                'elps_ci' => '1',
                'elps_gi' => '17',

                // MeeseeksMedia API credentials
                'meeseeks_api_key' => 'BA31CB52-2023-0F5E-26F1-17258C7B5CAA',

                // Novelix API credentials
                'novelix_api_key' => 'bANwHGbj4mxQFUdefk1i',
                'novelix_affid' => '16',

                // Tigloo API credentials
                'tigloo_username' => 'SECH',
                'tigloo_password' => 'Ss1234@',
                'tigloo_api_key' => '2643889w34df345676ssdas323tgc738',
                'tigloo_ai' => '2958531',
                'tigloo_ci' => '821',
                'tigloo_gi' => '545',

                // Koi API credentials
                'koi_api_key' => 'D39501B5-4872-3F35-3463-EC6B258BE52A',

                // Pastile API credentials
                'pastile_username' => 'CFmeeseeks',
                'pastile_password' => '3OxW)n(8_9',
                'pastile_api_key' => '2643889w34df345676ssdas323tgc738',
                'pastile_ai' => '2958073',
                'pastile_ci' => '1',
                'pastile_gi' => '55',

                // Pixel Management URLs
                'facebook_pixel_url' => 'https://conversionpixel.com/fb.php',
                'second_pixel_url' => 'http://plz.hold1sec.com/postback',
            ]
        );

        $this->command->info('Admin API credentials seeded successfully!');
    }
}
