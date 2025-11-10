<?php

namespace App\Models;

use App\Models\Modules\Dropservicing\UserGig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DropservicingMarketingPlan extends Model
{
    protected $fillable = [
        'user_id',
        'gig_id',
        'plan_type',
        'input_summary',
        'ai_output',
        'credit_cost',
        'status',
        'tokens_used',
    ];

    protected $casts = [
        'input_summary' => 'array',
        'credit_cost' => 'integer',
        'tokens_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gig(): BelongsTo
    {
        return $this->belongsTo(UserGig::class, 'gig_id');
    }

    public function fulfillmentLog(): HasOne
    {
        return $this->hasOne(AIFulfillmentLog::class, 'marketing_plan_id');
    }

    /**
     * Get credit cost for plan type
     */
    public static function getCreditCost(string $planType): int
    {
        // Try to get from settings first, then config, then defaults
        $settings = \App\Models\Setting::where('key', 'marketing_plan_costs')->first();
        
        if ($settings) {
            $costs = json_decode($settings->value, true);
            if (isset($costs[$planType])) {
                return (int) $costs[$planType];
            }
        }

        $costs = config('dropservicing.marketing_plan_costs', [
            '7-day' => 8,
            '30-day' => 20,
            'ads-only' => 10,
            'content-calendar' => 12,
        ]);

        return $costs[$planType] ?? 10;
    }
}
