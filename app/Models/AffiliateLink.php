<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'offer_id',
        'tracking_id',
        'tracking_link',
        'total_clicks',
        'total_conversions',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }
}

