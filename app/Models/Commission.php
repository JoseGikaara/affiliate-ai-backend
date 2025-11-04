<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'conversion_id',
        'amount',
        'payout_rate',
        'conversion_value',
        'date',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payout_rate' => 'decimal:2',
        'conversion_value' => 'decimal:2',
        'date' => 'date',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }
}

