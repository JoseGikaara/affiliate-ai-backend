<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayoutRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'amount',
        'currency',
        'payout_method',
        'account_details',
        'status',
        'admin_notes',
        'external_txn_id',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'account_details' => 'array',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }
}

