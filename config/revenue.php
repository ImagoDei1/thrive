<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Setup fee (reduced revenue share)
    |--------------------------------------------------------------------------
    |
    | Providers pay no upfront setup fee. Instead, for their first months on the
    | platform, `percentage` points of the revenue attributable to their watch
    | time are withheld and kept by the platform. On an 80/20 split with a 10
    | point setup fee, the provider receives 70% instead of 80%.
    |
    | These are the defaults applied on KYC approval; admins can adjust terms
    | per provider. The period can never exceed `max_months`.
    |
    */

    'setup_fee' => [
        'percentage'     => (float) env('SETUP_FEE_PERCENTAGE', 10),
        'months'         => (int) env('SETUP_FEE_MONTHS', 6),
        'max_months'     => 6,
        'max_percentage' => 50,
    ],

];
