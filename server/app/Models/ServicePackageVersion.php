<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'package_id',
        'version_number',
        'snapshot',
        'reason',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'version_number' => 'integer',
        'created_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'package_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
