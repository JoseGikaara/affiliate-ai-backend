<?php

namespace App\Models\Modules\Dropservicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GigOrder extends Model
{
    protected $fillable = [
        'gig_id',
        'buyer_email',
        'requirements',
        'total_price',
        'status',
        'paypal_transaction_id',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];

    public function gig(): BelongsTo
    {
        return $this->belongsTo(UserGig::class, 'gig_id');
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(GigFulfillment::class, 'order_id');
    }
}
