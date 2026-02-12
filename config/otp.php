<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP Testing Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, OTP generation will bypass SMS sending and return the
    | OTP code in the response for testing purposes. The OTP will be
    | displayed in the modal for easy testing.
    |
    | Set to true to enable testing mode, false to use production SMS sending.
    |
    */

    'testing_mode' => env('OTP_TESTING_MODE', false),

];

