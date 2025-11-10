<?php

namespace App\Models\Modules\Dropservicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GigFulfillment extends Model
{
    protected $fillable = [
        'order_id',
        'ai_output',
        'file_url',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GigOrder::class, 'order_id');
    }
}
