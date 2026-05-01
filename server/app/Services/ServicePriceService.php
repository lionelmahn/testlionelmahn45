<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicePriceService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    /**
     * Auto-generate the initial price record when a service is first created
     * with a positive price. Called by ServiceCatalogService::createService.
     */
    public function createInitialPrice(Service $service, float $price, ?User $actor): ?ServicePrice
    {
        if ($price <= 0) {
            return null;
        }

        $now = now();

        return DB::transaction(function () use ($service, $price, $actor, $now) {
            $record = ServicePrice::create([
                'service_id' => $service->id,
                'price' => $price,
                'currency_code' => 'VND',
                'is_tax_inclusive' => true,
                'effective_from' => $service->created_at ?? $now,
                'effective_to' => null,
                'status' => ServicePrice::STATUS_ACTIVE,
                'proposal_status' => ServicePrice::PROPOSAL_APPROVED,
                'reason' => 'Gia khoi tao tu UC4.1',
                'created_by' => $actor?->id,
                'approved_by' => $actor?->id,
                'approved_at' => $now,
            ]);

            $this->syncServicePriceCache($service);

            $this->auditLog->log($actor, 'service_price.initialized', [
                'service_id' => $service->id,
                'price_id' => $record->id,
                'price' => (float) $price,
            ]);

            return $record;
        });
    }

    /**
     * Create a new price record (admin direct or accountant proposal).
     *
     * @param  array<string,mixed>  $data
     */
    public function createPrice(int $serviceId, array $data, ?User $actor, bool $isProposal = false): ServicePrice
    {
        $service = Service::findOrFail($serviceId);
        $payload = $this->validatePayload($data);

        return DB::transaction(function () use ($service, $payload, $data, $actor, $isProposal) {
            $applyNow = (bool) ($data['apply_now'] ?? false);
            $effectiveFrom = $applyNow
                ? now()
                : Carbon::parse($payload['effective_from'])->setTime(0, 0, 0);
            $effectiveTo = $payload['effective_to']
                ? Carbon::parse($payload['effective_to'])->setTime(23, 59, 59)
                : null;

            // Lock all approved records of this service before checking overlaps.
            ServicePrice::query()
                ->where('service_id', $service->id)
                ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED])
                ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
                ->lockForUpdate()
                ->get();

            if (! $isProposal && ! $applyNow) {
                // For a future-dated record, cap any currently-effective open-ended
                // record at newFrom-1s so it is naturally superseded when the new
                // record activates. Without this, the open-ended record's range
                // [from, infinity] always overlaps with the new record.
                $this->capOpenEndedSupersededRecords($service->id, $effectiveFrom);

                // Apply-now path closes existing active records via endActiveRecordsBefore()
                // immediately after insert, so we skip the overlap check for that case.
                // (Scheduled future-dated overlaps for non-apply-now still validated.)
                $this->ensureNoOverlap($service->id, $effectiveFrom, $effectiveTo);
            }

            $status = $isProposal
                ? ServicePrice::STATUS_SCHEDULED
                : ($applyNow
                    ? ServicePrice::STATUS_ACTIVE
                    : ($effectiveFrom->lessThanOrEqualTo(now())
                        ? ServicePrice::STATUS_ACTIVE
                        : ServicePrice::STATUS_SCHEDULED));

            $record = ServicePrice::create([
                'service_id' => $service->id,
                'price' => $payload['price'],
                'currency_code' => 'VND',
                'is_tax_inclusive' => true,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'status' => $status,
                'proposal_status' => $isProposal
                    ? ServicePrice::PROPOSAL_PENDING
                    : ServicePrice::PROPOSAL_APPROVED,
                'reason' => $payload['reason'],
                'created_by' => $actor?->id,
                'proposed_by' => $isProposal ? $actor?->id : null,
                'approved_by' => $isProposal ? null : $actor?->id,
                'approved_at' => $isProposal ? null : now(),
            ]);

            if (! $isProposal && $applyNow) {
                $this->endActiveRecordsBefore($service->id, $effectiveFrom, $record->id, $effectiveTo);
                $this->syncServicePriceCache($service->fresh());
            } elseif (! $isProposal && $status === ServicePrice::STATUS_ACTIVE) {
                $this->syncServicePriceCache($service->fresh());
            }

            $this->auditLog->log($actor, $isProposal ? 'service_price.proposed' : 'service_price.created', [
                'service_id' => $service->id,
                'price_id' => $record->id,
                'price' => (float) $record->price,
                'effective_from' => $effectiveFrom->toIso8601String(),
                'effective_to' => $effectiveTo?->toIso8601String(),
                'apply_now' => $applyNow,
            ]);

            return $record->fresh(['service:id,service_code,name', 'creator:id,name', 'proposer:id,name']);
        });
    }

    /**
     * Edit a future-dated record (scheduled, not yet active) or pending proposal.
     * E5: cannot edit past/active records.
     *
     * @param  array<string,mixed>  $data
     */
    public function updatePrice(int $id, array $data, ?User $actor): ServicePrice
    {
        $payload = $this->validatePayload($data);

        return DB::transaction(function () use ($id, $payload, $actor) {
            $record = ServicePrice::query()->lockForUpdate()->findOrFail($id);

            // E5: cannot edit a record that is already serving as the effective
            // price (active or scheduled-with-effective_from-already-passed) or
            // that has expired. Approved records that started in the past are
            // immutable regardless of their `status` column value (no cron
            // transitions SCHEDULED -> ACTIVE so we must guard on time).
            $startedAlready = $record->effective_from && $record->effective_from->lessThanOrEqualTo(now());
            $isApprovedAndStarted = $record->proposal_status === ServicePrice::PROPOSAL_APPROVED
                && in_array($record->status, [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED], true)
                && $startedAlready;

            if ($record->status === ServicePrice::STATUS_EXPIRED || $isApprovedAndStarted) {
                throw ValidationException::withMessages([
                    'id' => 'Khong the chinh sua ban ghi gia da hoac dang co hieu luc (E5).',
                ]);
            }

            if ($record->proposal_status === ServicePrice::PROPOSAL_REJECTED) {
                throw ValidationException::withMessages([
                    'id' => 'Ban ghi de xuat da bi tu choi, khong the chinh sua.',
                ]);
            }

            $effectiveFrom = Carbon::parse($payload['effective_from'])->setTime(0, 0, 0);
            $effectiveTo = $payload['effective_to']
                ? Carbon::parse($payload['effective_to'])->setTime(23, 59, 59)
                : null;

            ServicePrice::query()
                ->where('service_id', $record->service_id)
                ->where('id', '!=', $record->id)
                ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED])
                ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
                ->lockForUpdate()
                ->get();

            if ($record->proposal_status === ServicePrice::PROPOSAL_APPROVED) {
                $this->capOpenEndedSupersededRecords($record->service_id, $effectiveFrom, $record->id);
                $this->ensureNoOverlap($record->service_id, $effectiveFrom, $effectiveTo, excludeId: $record->id);
            }

            $record->fill([
                'price' => $payload['price'],
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'reason' => $payload['reason'],
            ])->save();

            $this->auditLog->log($actor, 'service_price.updated', [
                'service_id' => $record->service_id,
                'price_id' => $record->id,
                'price' => (float) $record->price,
            ]);

            return $record->fresh(['service:id,service_code,name', 'creator:id,name']);
        });
    }

    public function deletePrice(int $id, ?User $actor): void
    {
        DB::transaction(function () use ($id, $actor) {
            $record = ServicePrice::query()->lockForUpdate()->findOrFail($id);

            // E6: cannot delete an approved record that is currently serving as
            // the effective price. Both ACTIVE and SCHEDULED records whose
            // effective_from has passed qualify (priceAt() reads both statuses).
            $startedAlready = $record->effective_from && $record->effective_from->lessThanOrEqualTo(now());
            $isApprovedAndStarted = $record->proposal_status === ServicePrice::PROPOSAL_APPROVED
                && in_array($record->status, [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED], true)
                && $startedAlready;

            if ($isApprovedAndStarted) {
                throw ValidationException::withMessages([
                    'id' => 'Khong the xoa ban ghi gia dang co hieu luc (E6).',
                ]);
            }

            if ($record->status === ServicePrice::STATUS_EXPIRED) {
                throw ValidationException::withMessages([
                    'id' => 'Khong the xoa ban ghi gia da het hieu luc (E6).',
                ]);
            }

            $serviceId = $record->service_id;
            $recordId = $record->id;
            $record->delete();

            $this->auditLog->log($actor, 'service_price.deleted', [
                'service_id' => $serviceId,
                'price_id' => $recordId,
            ]);
        });
    }

    public function approveProposal(int $id, ?User $actor): ServicePrice
    {
        return DB::transaction(function () use ($id, $actor) {
            // Lock the proposal row itself first so a concurrent approve/reject
            // cannot race against the pending check.
            $record = ServicePrice::query()->lockForUpdate()->findOrFail($id);

            if ($record->proposal_status !== ServicePrice::PROPOSAL_PENDING) {
                throw ValidationException::withMessages([
                    'id' => 'Chi co the duyet de xuat dang cho duyet.',
                ]);
            }

            ServicePrice::query()
                ->where('service_id', $record->service_id)
                ->where('id', '!=', $record->id)
                ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED])
                ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
                ->lockForUpdate()
                ->get();

            $isImmediate = $record->effective_from->lessThanOrEqualTo(now());

            // For a future-dated proposal, cap any open-ended predecessor so the
            // overlap check does not falsely reject it. (Immediate approval is
            // handled below by endActiveRecordsBefore which fully expires the
            // current active record.)
            if (! $isImmediate) {
                $this->capOpenEndedSupersededRecords($record->service_id, $record->effective_from, $record->id);
            }

            $this->ensureNoOverlap(
                $record->service_id,
                $record->effective_from,
                $record->effective_to,
                excludeId: $record->id
            );

            $record->fill([
                'proposal_status' => ServicePrice::PROPOSAL_APPROVED,
                'status' => $isImmediate ? ServicePrice::STATUS_ACTIVE : ServicePrice::STATUS_SCHEDULED,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ])->save();

            if ($isImmediate) {
                $this->endActiveRecordsBefore($record->service_id, $record->effective_from, $record->id, $record->effective_to);
                $this->syncServicePriceCache($record->service);
            }

            $this->auditLog->log($actor, 'service_price.approved', [
                'service_id' => $record->service_id,
                'price_id' => $record->id,
            ]);

            return $record->fresh(['service:id,service_code,name', 'proposer:id,name', 'approver:id,name']);
        });
    }

    public function rejectProposal(int $id, ?string $reason, ?User $actor): ServicePrice
    {
        return DB::transaction(function () use ($id, $reason, $actor) {
            $record = ServicePrice::query()->lockForUpdate()->findOrFail($id);

            if ($record->proposal_status !== ServicePrice::PROPOSAL_PENDING) {
                throw ValidationException::withMessages([
                    'id' => 'Chi co the tu choi de xuat dang cho duyet.',
                ]);
            }

            $record->fill([
                'proposal_status' => ServicePrice::PROPOSAL_REJECTED,
                'status' => ServicePrice::STATUS_CANCELLED,
                'rejected_reason' => $reason,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ])->save();

            $this->auditLog->log($actor, 'service_price.rejected', [
                'service_id' => $record->service_id,
                'price_id' => $record->id,
                'reason' => $reason,
            ]);

            return $record->fresh(['service:id,service_code,name', 'proposer:id,name', 'approver:id,name']);
        });
    }

    /**
     * Resolve the active price for a service at a given datetime.
     * Falls back to services.price cache if no record matches (E4 safety net).
     */
    public function priceAt(int $serviceId, ?CarbonInterface $datetime = null): ?float
    {
        $when = $datetime ?? now();

        $record = ServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
            ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED, ServicePrice::STATUS_EXPIRED])
            ->where('effective_from', '<=', $when)
            ->where(function (Builder $q) use ($when) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $when);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($record) {
            return (float) $record->price;
        }

        $cached = Service::query()->where('id', $serviceId)->value('price');

        return $cached !== null ? (float) $cached : null;
    }

    /**
     * Validate that no overlap exists between [$from, $to] and approved records of the service.
     */
    public function ensureNoOverlap(int $serviceId, CarbonInterface $from, ?CarbonInterface $to, ?int $excludeId = null): void
    {
        $q = ServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
            ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED]);

        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }

        $q->where(function (Builder $sub) use ($from, $to) {
            // Existing range [ef, et]; new range [from, to]
            // Overlap if: from <= et (or et IS NULL) AND (to >= ef OR to IS NULL)
            $sub->where(function (Builder $left) use ($from) {
                $left->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
            })->where(function (Builder $right) use ($to) {
                if ($to === null) {
                    // open-ended new range overlaps with anything from ef onwards
                    return;
                }
                $right->where('effective_from', '<=', $to);
            });
        });

        $conflict = $q->first();
        if ($conflict) {
            throw ValidationException::withMessages([
                'effective_from' => sprintf(
                    'Thoi gian hieu luc bi chong lan voi ban ghi gia ID #%d (%s - %s) (E3).',
                    $conflict->id,
                    optional($conflict->effective_from)->format('d/m/Y') ?? '-',
                    optional($conflict->effective_to)->format('d/m/Y') ?? 'khong thoi han'
                ),
            ]);
        }
    }

    /**
     * List services with their current price + counts of pending proposals.
     *
     * @param  array<string,mixed>  $filters
     */
    public function listGroupedByService(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        $q = Service::query()
            ->with([
                'group:id,name',
                'activePrice',
            ])
            ->select(['id', 'service_code', 'name', 'service_group_id', 'price', 'status']);

        if (! empty($filters['search'])) {
            $term = '%'.Str::of($filters['search'])->trim().'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhere('service_code', 'like', $term);
            });
        }

        if (! empty($filters['service_group_id']) && $filters['service_group_id'] !== 'all') {
            $q->where('service_group_id', (int) $filters['service_group_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $q->where('status', $filters['status']);
        }

        if (! empty($filters['only']) && $filters['only'] === 'with_pending') {
            $q->whereExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('service_prices')
                    ->whereColumn('service_prices.service_id', 'services.id')
                    ->where('service_prices.proposal_status', ServicePrice::PROPOSAL_PENDING);
            });
        }

        return $q->orderBy('id')->paginate($perPage);
    }

    public function timelineForService(int $serviceId): array
    {
        $service = Service::query()
            ->with('group:id,name')
            ->select(['id', 'service_code', 'name', 'service_group_id', 'price', 'status'])
            ->findOrFail($serviceId);

        $now = now();
        $records = ServicePrice::query()
            ->where('service_id', $serviceId)
            ->with(['creator:id,name', 'proposer:id,name', 'approver:id,name'])
            ->orderByDesc('effective_from')
            ->get();

        $current = null;
        $future = [];
        $past = [];
        $pending = [];

        foreach ($records as $r) {
            if ($r->proposal_status === ServicePrice::PROPOSAL_PENDING) {
                $pending[] = $r;

                continue;
            }
            if ($r->proposal_status === ServicePrice::PROPOSAL_REJECTED
                || $r->status === ServicePrice::STATUS_CANCELLED) {
                continue;
            }
            $effectiveTo = $r->effective_to;
            $isCurrent = $r->effective_from && $r->effective_from->lessThanOrEqualTo($now)
                && ($effectiveTo === null || $effectiveTo->greaterThanOrEqualTo($now));

            if ($isCurrent) {
                $current = $r;
            } elseif ($r->effective_from && $r->effective_from->greaterThan($now)) {
                $future[] = $r;
            } else {
                $past[] = $r;
            }
        }

        return [
            'service' => $service,
            'current' => $current,
            'future' => array_values($future),
            'past' => array_values($past),
            'pending' => array_values($pending),
        ];
    }

    public function listPendingProposals(): array
    {
        return ServicePrice::query()
            ->with(['service:id,service_code,name', 'proposer:id,name'])
            ->where('proposal_status', ServicePrice::PROPOSAL_PENDING)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * @return array<int,mixed>
     */
    public function recentAuditLogs(): array
    {
        return DB::table('audit_logs')
            ->where('action', 'like', 'service_price.%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * Validate raw input from controllers.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function validatePayload(array $data): array
    {
        $errors = [];

        $price = $data['price'] ?? null;
        if (! is_numeric($price)) {
            $errors['price'] = 'Gia phai la so.';
        } elseif ((float) $price <= 0) {
            $errors['price'] = 'Gia phai lon hon 0 (E1).';
        }

        if (empty($data['effective_from'])) {
            $errors['effective_from'] = 'Thoi gian hieu luc (tu) la bat buoc (E8).';
        }

        if (! empty($data['effective_from']) && ! empty($data['effective_to'])) {
            $from = Carbon::parse($data['effective_from']);
            $to = Carbon::parse($data['effective_to']);
            if ($from->greaterThan($to)) {
                $errors['effective_to'] = 'Thoi gian hieu luc den phai sau hieu luc tu (E2).';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'price' => (float) $price,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'reason' => $data['reason'] ?? null,
        ];
    }

    /**
     * Cap any currently-effective approved record (i.e. one that has already
     * started, effective_from <= now) so that it naturally ends just before
     * $newFrom. Used when scheduling a future-dated record so its range does
     * not collide with an open-ended predecessor in ensureNoOverlap.
     *
     * Only currently-effective records are capped — future scheduled records
     * (effective_from > now) are NOT touched, because two future scheduled
     * records starting in the same window are a real conflict that should be
     * detected by ensureNoOverlap.
     */
    private function capOpenEndedSupersededRecords(int $serviceId, CarbonInterface $newFrom, ?int $excludeId = null): void
    {
        $now = now();
        $boundary = $newFrom->copy()->subSecond();

        $q = ServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
            ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED])
            ->where('effective_from', '<=', $now)
            ->where('effective_from', '<', $newFrom)
            ->where(function (Builder $sub) use ($newFrom) {
                // Open-ended OR ends after newFrom (records ending before
                // newFrom are already non-overlapping; leave them alone).
                $sub->whereNull('effective_to')->orWhere('effective_to', '>=', $newFrom);
            });

        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }

        $q->update(['effective_to' => $boundary]);
    }

    /**
     * When a new active record [newFrom, newTo] is created (apply-now or
     * approving an immediate proposal), supersede prior records:
     *   - Active records (currently in effect) -> expire at newFrom - 1s.
     *   - Scheduled records whose effective_from is within [newFrom, newTo]
     *     -> cancel (they are made redundant by the new active record's range).
     *
     * Scheduled records strictly after newTo (when newTo is bounded) are kept.
     */
    private function endActiveRecordsBefore(int $serviceId, CarbonInterface $newFrom, int $excludeId, ?CarbonInterface $newTo = null): void
    {
        $boundary = $newFrom->copy()->subSecond();

        ServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('id', '!=', $excludeId)
            ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->update([
                'effective_to' => $boundary,
                'status' => ServicePrice::STATUS_EXPIRED,
            ]);

        $scheduledQuery = ServicePrice::query()
            ->where('service_id', $serviceId)
            ->where('id', '!=', $excludeId)
            ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
            ->where('status', ServicePrice::STATUS_SCHEDULED)
            ->where('effective_from', '>=', $newFrom);

        if ($newTo !== null) {
            $scheduledQuery->where('effective_from', '<=', $newTo);
        }

        $scheduledQuery->update([
            'status' => ServicePrice::STATUS_CANCELLED,
        ]);
    }

    /**
     * Update services.price denormalized cache to reflect the active price.
     */
    private function syncServicePriceCache(Service $service): void
    {
        $active = ServicePrice::query()
            ->where('service_id', $service->id)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
            ->orderByDesc('effective_from')
            ->first();

        if ($active && (float) $active->price !== (float) $service->price) {
            $service->price = $active->price;
            $service->saveQuietly();
        }
    }
}
