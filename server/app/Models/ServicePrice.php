<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    public const PROPOSAL_APPROVED = 'approved';
    public const PROPOSAL_PENDING = 'pending';
    public const PROPOSAL_REJECTED = 'rejected';

    public const PROPOSAL_STATUSES = [
        self::PROPOSAL_APPROVED,
        self::PROPOSAL_PENDING,
        self::PROPOSAL_REJECTED,
    ];

    protected $table = 'service_prices';

    protected $fillable = [
        'service_id',
        'price',
        'currency_code',
        'is_tax_inclusive',
        'effective_from',
        'effective_to',
        'status',
        'proposal_status',
        'reason',
        'rejected_reason',
        'proposed_by',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_tax_inclusive' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
