<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DropservicingService extends Model
{
    protected $fillable = [
        'name',
        'description',
        'credit_cost',
        'ai_prompt_template',
        'delivery_time',
        'status',
        'input_fields',
    ];

    protected $casts = [
        'input_fields' => 'array',
        'credit_cost' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(DropservicingOrder::class, 'service_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
