<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'affiliate_link_id',
        'tracking_id',
        'conversion_value',
        'status',
        'metadata',
    ];

    protected $casts = [
        'conversion_value' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}

