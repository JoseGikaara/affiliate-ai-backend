<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Affiliate Network Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Define setup and renewal credit costs for each affiliate network.
    | Setup credits: One-time cost to generate the landing page
    | Renewal credits: Monthly recurring cost to host the page
    |
    */

    'networks' => [
        'Fiverr' => [
            'setup_credits' => 5,
            'renewal_credits' => 2,
            'template_type' => 'freelance_services',
        ],
        'Udemy' => [
            'setup_credits' => 6,
            'renewal_credits' => 3,
            'template_type' => 'course_promotion',
        ],
        'Digistore24' => [
            'setup_credits' => 5,
            'renewal_credits' => 3,
            'template_type' => 'digital_product',
        ],
        'CPA Grip' => [
            'setup_credits' => 3,
            'renewal_credits' => 1,
            'template_type' => 'lead_gen',
        ],
        'Deriv' => [
            'setup_credits' => 8,
            'renewal_credits' => 4,
            'template_type' => 'forex_broker',
        ],
        'Exness' => [
            'setup_credits' => 8,
            'renewal_credits' => 4,
            'template_type' => 'forex_broker',
        ],
        'HF Markets' => [
            'setup_credits' => 8,
            'renewal_credits' => 4,
            'template_type' => 'forex_broker',
        ],
        'IC Markets' => [
            'setup_credits' => 8,
            'renewal_credits' => 4,
            'template_type' => 'forex_broker',
        ],
        'Pocket Option' => [
            'setup_credits' => 8,
            'renewal_credits' => 4,
            'template_type' => 'forex_broker',
        ],
        'Jumia' => [
            'setup_credits' => 5,
            'renewal_credits' => 3,
            'template_type' => 'product_showcase',
        ],
        'Amazon Associates' => [
            'setup_credits' => 5,
            'renewal_credits' => 3,
            'template_type' => 'product_showcase',
        ],
        'Shopify' => [
            'setup_credits' => 5,
            'renewal_credits' => 3,
            'template_type' => 'product_showcase',
        ],
        'OGAds' => [
            'setup_credits' => 3,
            'renewal_credits' => 1,
            'template_type' => 'lead_gen',
        ],
        'MaxBounty' => [
            'setup_credits' => 3,
            'renewal_credits' => 1,
            'template_type' => 'lead_gen',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Network Categories for Frontend Grouping
    |--------------------------------------------------------------------------
    */

    'categories' => [
        'Freelancing & Services' => ['Fiverr'],
        'Education & Learning' => ['Udemy'],
        'Forex & Trading' => ['Deriv', 'Exness', 'HF Markets', 'IC Markets', 'Pocket Option'],
        'E-commerce' => ['Jumia', 'Amazon Associates', 'Shopify', 'Digistore24'],
        'CPA / Lead Gen' => ['CPA Grip', 'OGAds', 'MaxBounty'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Network Configuration
    |--------------------------------------------------------------------------
    */

    'default' => [
        'setup_credits' => 5,
        'renewal_credits' => 2,
        'template_type' => 'generic',
    ],
];

