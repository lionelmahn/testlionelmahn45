<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToothStatusHistory extends Model
{
    protected $table = 'tooth_status_history';

    public $timestamps = false;

    protected $fillable = [
        'tooth_status_id',
        'action',
        'before',
        'after',
        'note',
        'performed_by',
        'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function toothStatus(): BelongsTo
    {
        return $this->belongsTo(ToothStatus::class);
    }
}
