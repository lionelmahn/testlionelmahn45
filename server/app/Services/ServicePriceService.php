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
                $this->endActiveRecordsBefore($service->id, $effectiveFrom, $record->id);
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
        $record = ServicePrice::findOrFail($id);

        if ($record->status === ServicePrice::STATUS_EXPIRED
            || ($record->status === ServicePrice::STATUS_ACTIVE
                && $record->effective_from && $record->effective_from->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages([
                'id' => 'Khong the chinh sua ban ghi gia da hoac dang co hieu luc (E5).',
            ]);
        }

        if ($record->proposal_status === ServicePrice::PROPOSAL_REJECTED) {
            throw ValidationException::withMessages([
                'id' => 'Ban ghi de xuat da bi tu choi, khong the chinh sua.',
            ]);
        }

        $payload = $this->validatePayload($data);

        return DB::transaction(function () use ($record, $payload, $data, $actor) {
            $effectiveFrom = Carbon::parse($payload['effective_from'])->setTime(0, 0, 0);
            $effectiveTo = $payload['effective_to']
                ? Carbon::parse($payload['effective_to'])->setTime(23, 59, 59)
                : null;

            ServicePrice::query()
                ->where('service_id', $record->service_id)
                ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED])
                ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
                ->lockForUpdate()
                ->get();

            if ($record->proposal_status === ServicePrice::PROPOSAL_APPROVED) {
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
        $record = ServicePrice::findOrFail($id);

        if ($record->status === ServicePrice::STATUS_ACTIVE
            && $record->effective_from && $record->effective_from->lessThanOrEqualTo(now())) {
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
        $record->delete();

        $this->auditLog->log($actor, 'service_price.deleted', [
            'service_id' => $serviceId,
            'price_id' => $id,
        ]);
    }

    public function approveProposal(int $id, ?User $actor): ServicePrice
    {
        $record = ServicePrice::findOrFail($id);

        if ($record->proposal_status !== ServicePrice::PROPOSAL_PENDING) {
            throw ValidationException::withMessages([
                'id' => 'Chi co the duyet de xuat dang cho duyet.',
            ]);
        }

        return DB::transaction(function () use ($record, $actor) {
            ServicePrice::query()
                ->where('service_id', $record->service_id)
                ->whereIn('status', [ServicePrice::STATUS_ACTIVE, ServicePrice::STATUS_SCHEDULED])
                ->where('proposal_status', ServicePrice::PROPOSAL_APPROVED)
                ->lockForUpdate()
                ->get();

            $this->ensureNoOverlap(
                $record->service_id,
                $record->effective_from,
                $record->effective_to,
                excludeId: $record->id
            );

            $isImmediate = $record->effective_from->lessThanOrEqualTo(now());

            $record->fill([
                'proposal_status' => ServicePrice::PROPOSAL_APPROVED,
                'status' => $isImmediate ? ServicePrice::STATUS_ACTIVE : ServicePrice::STATUS_SCHEDULED,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ])->save();

            if ($isImmediate) {
                $this->endActiveRecordsBefore($record->service_id, $record->effective_from, $record->id);
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
        $record = ServicePrice::findOrFail($id);

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
     * When a new active record is created, end any prior active records by setting
     * their effective_to = newFrom - 1 second and status = expired.
     */
    private function endActiveRecordsBefore(int $serviceId, CarbonInterface $newFrom, int $excludeId): void
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
