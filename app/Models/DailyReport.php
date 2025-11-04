<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'report_date',
        'total_clicks',
        'total_conversions',
        'total_earnings',
        'summary',
    ];

    protected $casts = [
        'report_date' => 'date',
        'total_earnings' => 'decimal:2',
        'summary' => 'array',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}

