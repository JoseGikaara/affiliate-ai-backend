<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BillingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'landing_page_id',
        'type',
        'credits_deducted',
        'status',
        'message',
    ];

    protected $casts = [
        'credits_deducted' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
