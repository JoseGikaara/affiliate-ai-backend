<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Minimum Payout Amount
    |--------------------------------------------------------------------------
    |
    | The minimum amount required to request a payout.
    |
    */
    'min_amount' => env('MIN_PAYOUT_AMOUNT', 500),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for payouts.
    |
    */
    'default_currency' => env('PAYOUT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Automated Payouts
    |--------------------------------------------------------------------------
    |
    | Whether automated payouts via payment providers are enabled.
    | Set to false by default for safety. Only enable in production
    | after testing thoroughly.
    |
    */
    'automated' => env('PAYOUTS_AUTOMATED', false),

    /*
    |--------------------------------------------------------------------------
    | Max Payouts Per Day
    |--------------------------------------------------------------------------
    |
    | Maximum number of payout requests allowed per affiliate per day.
    |
    */
    'max_per_day' => env('MAX_PAYOUTS_PER_DAY', 3),

    /*
    |--------------------------------------------------------------------------
    | Large Amount Threshold
    |--------------------------------------------------------------------------
    |
    | Amounts above this threshold require additional admin confirmation.
    |
    */
    'large_amount_threshold' => env('LARGE_PAYOUT_THRESHOLD', 10000),

    /*
    |--------------------------------------------------------------------------
    | PayPal Configuration
    |--------------------------------------------------------------------------
    */
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'secret' => env('PAYPAL_SECRET'),
        'env' => env('PAYPAL_ENV', 'sandbox'), // sandbox or live
        'fee_percentage' => env('PAYPAL_FEE_PERCENTAGE', 2.9),
        'fee_fixed' => env('PAYPAL_FEE_FIXED', 0.30),
    ],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Configuration
    |--------------------------------------------------------------------------
    */
    'mpesa' => [
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'env' => env('MPESA_ENV', 'sandbox'), // sandbox or production
        'fee_percentage' => env('MPESA_FEE_PERCENTAGE', 0),
        'fee_fixed' => env('MPESA_FEE_FIXED', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payout Processing
    |--------------------------------------------------------------------------
    */
    'processing' => [
        'queue' => env('PAYOUT_QUEUE', 'default'),
        'retry_attempts' => env('PAYOUT_RETRY_ATTEMPTS', 3),
        'timeout' => env('PAYOUT_TIMEOUT', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'admin_emails' => explode(',', env('ADMIN_EMAILS', '')),
        'send_on_request' => env('NOTIFY_ON_PAYOUT_REQUEST', true),
        'send_on_approval' => env('NOTIFY_ON_PAYOUT_APPROVAL', true),
        'send_on_completion' => env('NOTIFY_ON_PAYOUT_COMPLETION', true),
        'send_on_failure' => env('NOTIFY_ON_PAYOUT_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Reports
    |--------------------------------------------------------------------------
    */
    'reports' => [
        'enabled' => env('PAYOUT_REPORTS_ENABLED', true),
        'schedule_time' => env('PAYOUT_REPORT_TIME', '00:00'), // Daily at midnight
        'recipients' => explode(',', env('PAYOUT_REPORT_RECIPIENTS', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pending Payout Reminders
    |--------------------------------------------------------------------------
    */
    'reminders' => [
        'enabled' => env('PAYOUT_REMINDERS_ENABLED', true),
        'days_old' => env('PAYOUT_REMINDER_DAYS', 7), // Remind after 7 days
    ],
];

