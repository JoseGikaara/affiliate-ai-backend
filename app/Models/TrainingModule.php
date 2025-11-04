<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TrainingModule extends Model
{
    protected $fillable = [
        'network_id',
        'title',
        'content',
        'category',
        'preview_text',
        'thumbnail_url',
        'estimated_time',
        'difficulty',
        'credit_cost',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(AffiliateNetwork::class, 'network_id');
    }

    public function unlockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_training_unlocks', 'training_id', 'user_id')->withTimestamps();
    }
}


