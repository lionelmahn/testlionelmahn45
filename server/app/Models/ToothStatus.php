<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToothStatus extends Model
{
    protected $table = 'tooth_statuses';

    protected $fillable = [
        'code',
        'name',
        'tooth_status_group_id',
        'color',
        'icon',
        'description',
        'notes',
        'display_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ToothStatusGroup::class, 'tooth_status_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ToothStatusHistory::class)->orderByDesc('created_at');
    }
}
