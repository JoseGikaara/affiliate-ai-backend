<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CpaLocker extends Model
{
    protected $fillable = [
        'title',
        'description',
        'cost',
        'file_url',
        'image',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'cost' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the admin who created this locker
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all unlocks for this locker
     */
    public function unlocks(): HasMany
    {
        return $this->hasMany(AffiliateUnlock::class, 'locker_id');
    }
}

