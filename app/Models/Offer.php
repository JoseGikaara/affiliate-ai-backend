<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'payout',
        'commission_rate',
        'conversion_type',
        'image_url',
        'status',
    ];

    protected $casts = [
        'payout' => 'decimal:2',
        'commission_rate' => 'decimal:2',
    ];

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function conversions()
    {
        return $this->hasMany(Conversion::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function affiliateOfferRates()
    {
        return $this->hasMany(AffiliateOfferRate::class);
    }
}

