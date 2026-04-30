<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_HIDDEN = 'hidden';
    public const STATUS_DISCONTINUED = 'discontinued';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_INTERNAL = 'internal';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_HIDDEN,
        self::STATUS_DISCONTINUED,
    ];

    public const VISIBILITIES = [
        self::VISIBILITY_PUBLIC,
        self::VISIBILITY_INTERNAL,
    ];

    protected $fillable = [
        'code',
        'name',
        'slug',
        'description',
        'image_path',
        'status',
        'visibility',
        'original_price',
        'package_price',
        'discount_amount',
        'discount_percent',
        'effective_from',
        'effective_to',
        'usage_validity_days',
        'conditions',
        'notes',
        'version_number',
        'parent_package_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'original_price' => 'decimal:2',
        'package_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'usage_validity_days' => 'integer',
        'version_number' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class, 'package_id')->orderBy('display_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServicePackageVersion::class, 'package_id')->orderByDesc('version_number');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ServicePackageHistory::class, 'package_id')->orderByDesc('created_at');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_package_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'parent_package_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
