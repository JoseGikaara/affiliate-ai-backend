<?php

namespace App\Models\Modules\Dropservicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    protected $fillable = ['name'];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
