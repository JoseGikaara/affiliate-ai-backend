<?php

namespace App\Models\Modules\Dropservicing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserGig extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'title',
        'description',
        'pricing_tiers',
        'paypal_email',
        'status',
        'slug',
        'last_renewed_at',
    ];

    protected $casts = [
        'pricing_tiers' => 'array',
        'last_renewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gig) {
            if (empty($gig->slug)) {
                $gig->slug = Str::slug($gig->title) . '-' . Str::random(6);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(GigOrder::class, 'gig_id');
    }
}
