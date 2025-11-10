<?php

namespace App\Models\Modules\Dropservicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'base_credit_cost',
        'ai_prompt_template',
    ];

    protected $casts = [
        'ai_prompt_template' => 'array',
        'base_credit_cost' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function userGigs(): HasMany
    {
        return $this->hasMany(UserGig::class, 'service_id');
    }
}
