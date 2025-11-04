<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateNetwork extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'detailed_description',
        'base_url',
        'registration_url',
        'logo_url',
        'category',
        'commission_rate',
        'payment_methods',
        'minimum_payout',
        'payout_frequency',
        'features',
        'pros',
        'cons',
        'learn_more_credit_cost',
        'country_availability',
        'is_active',
    ];

    protected $casts = [
        'country_availability' => 'array',
        'payment_methods' => 'array',
        'features' => 'array',
        'pros' => 'array',
        'cons' => 'array',
        'is_active' => 'boolean',
        'learn_more_credit_cost' => 'integer',
    ];

    /**
     * Get all landing pages using this network
     */
    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class);
    }

    /**
     * Get the base cost for this network category
     */
    public function getBaseCreditCost(): int
    {
        return config("credits.network_categories.{$this->category}.base_cost", 5);
    }
}
