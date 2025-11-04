<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAsset extends Model
{
    protected $fillable = [
        'landing_page_id',
        'type',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Get the landing page that owns this marketing asset
     */
    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
