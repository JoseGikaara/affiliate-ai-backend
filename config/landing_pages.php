<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landing Page Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default settings for landing page hosting.
    |
    */

    'renewal' => [
        'default_interval' => env('LANDING_PAGE_RENEWAL_INTERVAL', 30), // days
        'default_credit_cost' => env('LANDING_PAGE_DEFAULT_CREDIT_COST', 1), // credits per 30 days
        'grace_period' => env('LANDING_PAGE_GRACE_PERIOD', 3), // days before deactivation
    ],

    'notifications' => [
        'warning_days_before_renewal' => env('LANDING_PAGE_RENEWAL_WARNING_DAYS', 3),
        'low_balance_threshold' => env('LANDING_PAGE_LOW_BALANCE_THRESHOLD', 5), // credits
    ],

    'domain' => [
        'base_domain' => env('LANDING_PAGE_DOMAIN', 'affnet.app'),
    ],
];

