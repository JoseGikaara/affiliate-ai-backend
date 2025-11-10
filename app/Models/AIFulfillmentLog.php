<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIFulfillmentLog extends Model
{
    protected $fillable = [
        'order_id',
        'marketing_plan_id',
        'ai_model',
        'tokens_used',
        'success',
        'error_message',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'success' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DropservicingOrder::class, 'order_id');
    }

    public function marketingPlan(): BelongsTo
    {
        return $this->belongsTo(DropservicingMarketingPlan::class, 'marketing_plan_id');
    }
}
