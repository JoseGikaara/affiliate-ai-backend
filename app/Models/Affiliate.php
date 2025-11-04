<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'referral_id',
        'commission_rate',
        'total_clicks',
        'total_leads',
        'total_conversions',
        'commission_earned',
        'status',
        'payout_name',
        'payout_email',
        'payout_phone',
        'payout_account',
        'kyc_verified',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'commission_earned' => 'decimal:2',
        'payout_account' => 'array',
        'kyc_verified' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payoutRequests()
    {
        return $this->hasMany(PayoutRequest::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

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

    public function dailyReports()
    {
        return $this->hasMany(DailyReport::class);
    }

    public function offerRates()
    {
        return $this->hasMany(AffiliateOfferRate::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($affiliate) {
            if (empty($affiliate->referral_id)) {
                $affiliate->referral_id = 'AFF' . strtoupper(uniqid());
            }
        });
    }
}

