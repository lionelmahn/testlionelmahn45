<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToothStatusGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function statuses(): HasMany
    {
        return $this->hasMany(ToothStatus::class);
    }
}
