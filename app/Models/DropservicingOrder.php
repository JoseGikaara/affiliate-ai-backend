<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DropservicingOrder extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'input_data',
        'ai_response',
        'status',
        'credits_used',
    ];

    protected $casts = [
        'input_data' => 'array',
        'credits_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(DropservicingService::class, 'service_id');
    }

    public function fulfillmentLog(): HasOne
    {
        return $this->hasOne(AIFulfillmentLog::class, 'order_id');
    }
}
