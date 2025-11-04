<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LandingPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'affiliate_network_id',
        'network',
        'affiliate_link',
        'title',
        'content',
        'html_content',
        'ad_copy',
        'email_series',
        'campaign_goal',
        'subdomain',
        'domain',
        'status',
        'expires_at',
        'credit_cost',
        'setup_credits',
        'renewal_credits',
        'credits_used',
        'views',
        'conversions',
        'type',
        'ai_template_type',
        'metadata',
        'auto_renew',
        'next_renewal_date',
        'last_renewal_date',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'next_renewal_date' => 'datetime',
        'last_renewal_date' => 'datetime',
        'metadata' => 'array',
        'email_series' => 'array',
        'views' => 'integer',
        'conversions' => 'integer',
        'credit_cost' => 'integer',
        'setup_credits' => 'integer',
        'renewal_credits' => 'integer',
        'credits_used' => 'integer',
        'auto_renew' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function analytics()
    {
        return $this->hasMany(LandingPageAnalytic::class);
    }

    public function billingLogs()
    {
        return $this->hasMany(BillingLog::class);
    }

    public function affiliateNetwork(): BelongsTo
    {
        return $this->belongsTo(AffiliateNetwork::class);
    }

    public function marketingAssets()
    {
        return $this->hasMany(MarketingAsset::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 3): bool
    {
        return $this->expires_at 
            && $this->expires_at->isFuture() 
            && $this->expires_at->diffInDays(now()) <= $days;
    }

    /**
     * Get CTR (Click-Through Rate) as percentage
     */
    public function getCtrAttribute(): float
    {
        if ($this->views === 0) {
            return 0.0;
        }
        
        return round(($this->conversions / $this->views) * 100, 2);
    }

    /**
     * Update views count from analytics
     */
    public function updateViewsCount(): void
    {
        $uniqueViews = $this->analytics()
            ->where('event_type', 'view')
            ->where('created_at', '>=', now()->subDay())
            ->select('ip_address')
            ->distinct()
            ->count();
        
        // Update the cached views count
        $this->update(['views' => $this->views + $uniqueViews]);
    }

    /**
     * Update conversions count from analytics
     */
    public function updateConversionsCount(): void
    {
        $conversions = $this->analytics()
            ->where('event_type', 'conversion')
            ->where('created_at', '>=', now()->subDay())
            ->count();
        
        $this->update(['conversions' => $this->conversions + $conversions]);
    }

    /**
     * Check if landing page is due for renewal
     */
    public function getIsDueForRenewalAttribute(): bool
    {
        return $this->next_renewal_date 
            && $this->next_renewal_date->lte(now())
            && $this->auto_renew
            && $this->status === 'active';
    }

    /**
     * Get days until next renewal
     */
    public function getDaysUntilRenewalAttribute(): ?int
    {
        if (!$this->next_renewal_date) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->next_renewal_date, false));
    }

    /**
     * Get network pricing configuration
     */
    public static function getNetworkPricing(string $network): array
    {
        $networks = config('networks.networks', []);
        
        if (isset($networks[$network])) {
            return $networks[$network];
        }
        
        return config('networks.default', [
            'setup_credits' => 5,
            'renewal_credits' => 2,
            'template_type' => 'generic',
        ]);
    }

    /**
     * Get supported networks
     */
    public static function getSupportedNetworks(): array
    {
        return array_keys(config('networks.networks', []));
    }

    /**
     * Get networks by category
     */
    public static function getNetworksByCategory(): array
    {
        return config('networks.categories', []);
    }
}
