<?php

return [
    'cost_per_task' => env('CREDIT_COST_PER_TASK', 5),
    
    // Network category base costs
    'network_categories' => [
        'freelancing' => [
            'base_cost' => 5,
        ],
        'education' => [
            'base_cost' => 3,
        ],
        'cpa' => [
            'base_cost' => 2,
        ],
        'forex' => [
            'base_cost' => 6,
        ],
        'ecommerce' => [
            'base_cost' => 4,
        ],
    ],
    
    // Email automation multiplier
    'email_automation_multiplier' => env('EMAIL_AUTOMATION_MULTIPLIER', 1.2),
];


