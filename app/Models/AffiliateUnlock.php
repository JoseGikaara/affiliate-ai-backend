<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateUnlock extends Model
{
    protected $fillable = [
        'user_id',
        'locker_id',
        'credits_spent',
    ];

    protected $casts = [
        'credits_spent' => 'integer',
    ];

    /**
     * Get the user who unlocked this
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the CPA locker that was unlocked
     */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(CpaLocker::class, 'locker_id');
    }
}

