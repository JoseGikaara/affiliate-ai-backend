<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LandingPageAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'landing_page_id',
        'event_type',
        'ip_address',
        'user_agent',
        'referer',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
