<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marketing Plan Credit Costs
    |--------------------------------------------------------------------------
    |
    | Default credit costs for each marketing plan type. These can be
    | overridden via admin settings.
    |
    */

    'marketing_plan_costs' => [
        '7-day' => env('MARKETING_PLAN_7DAY_COST', 8),
        '30-day' => env('MARKETING_PLAN_30DAY_COST', 20),
        'ads-only' => env('MARKETING_PLAN_ADS_COST', 10),
        'content-calendar' => env('MARKETING_PLAN_CALENDAR_COST', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of marketing plan requests per user per 24 hours
    |
    */

    'max_plans_per_day' => env('MAX_MARKETING_PLANS_PER_DAY', 3),
];


