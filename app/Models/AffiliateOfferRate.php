<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateOfferRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'commission_rate',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}

