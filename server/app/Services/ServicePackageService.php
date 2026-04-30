<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\ServicePackageHistory;
use App\Models\ServicePackageItem;
use App\Models\ServicePackageVersion;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicePackageService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function listPackages(array $filters): LengthAwarePaginator
    {
        $query = ServicePackage::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->withCount('items');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['visibility']) && $filters['visibility'] !== 'all') {
            $query->where('visibility', $filters['visibility']);
        }

        if (! empty($filters['effective_from'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('effective_to', '>=', $filters['effective_from'])
                    ->orWhereNull('effective_to');
            });
        }
        if (! empty($filters['effective_to'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereDate('effective_from', '<=', $filters['effective_to'])
                    ->orWhereNull('effective_from');
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderByDesc('id')->paginate(min(max($perPage, 1), 100));
    }

    public function publicListPackages(array $filters): LengthAwarePaginator
    {
        $filters['visibility'] = ServicePackage::VISIBILITY_PUBLIC;
        $filters['status'] = ServicePackage::STATUS_ACTIVE;

        return $this->listPackages($filters);
    }

    public function findPackage(int $id): ServicePackage
    {
        return ServicePackage::with([
            'items.service:id,service_code,name,price,status',
            'versions.changer:id,name',
            'history.changer:id,name',
            'creator:id,name',
            'updater:id,name',
            'parent:id,code,name,version_number',
        ])->findOrFail($id);
    }

    public function createPackage(array $data, ?User $actor): ServicePackage
    {
        $payload = $this->sanitizePayload($data, isUpdate: false);
        $items = $this->validateAndPrepareItems($data['items'] ?? []);
        $this->validateDateRange($payload['effective_from'] ?? null, $payload['effective_to'] ?? null);
        $this->validateUsageValidity($payload['usage_validity_days'] ?? null);

        $this->ensureUniqueCode($payload['code']);
        $this->ensureUniqueName($payload['name']);

        $totals = $this->computeTotals($items, $payload['package_price'] ?? 0);
        $payload = array_merge($payload, $totals);

        $this->validateStatusRequirements($payload['status'], $items, $payload);

        return DB::transaction(function () use ($payload, $items, $data, $actor) {
            $payload['created_by'] = $actor?->id;
            $payload['updated_by'] = $actor?->id;
            $payload['version_number'] = 1;

            $package = ServicePackage::create($payload);

            $this->syncItems($package, $items);

            ServicePackageHistory::create([
                'package_id' => $package->id,
                'action' => 'created',
                'payload' => [
                    'name' => $package->name,
                    'status' => $package->status,
                    'item_count' => count($items),
                ],
                'reason' => $data['status_reason'] ?? 'Tao moi goi dich vu',
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $this->auditLog->log($actor, 'service_package.created', [
                'package_id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
                'status' => $package->status,
            ]);

            return $package->fresh(['items.service', 'creator:id,name']);
        });
    }

    public function updatePackage(int $id, array $data, ?User $actor): ServicePackage
    {
        $package = ServicePackage::with('items')->findOrFail($id);

        // E7: Goi da phat sinh giao dich -> bat buoc tao phien ban moi.
        if ($this->packageHasTransactions($package->id)) {
            throw ValidationException::withMessages([
                'package_id' => 'Goi da phat sinh giao dich, vui long Nhan ban / tao phien ban moi de chinh sua (E7).',
            ]);
        }

        $payload = $this->sanitizePayload($data, isUpdate: true, current: $package);
        $items = array_key_exists('items', $data)
            ? $this->validateAndPrepareItems($data['items'])
            : $package->items->map(fn ($i) => [
                'service_id' => $i->service_id,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'note' => $i->note,
                'display_order' => $i->display_order,
            ])->all();

        $this->validateDateRange(
            $payload['effective_from'] ?? $package->effective_from?->toDateString(),
            $payload['effective_to'] ?? $package->effective_to?->toDateString()
        );
        $this->validateUsageValidity($payload['usage_validity_days'] ?? $package->usage_validity_days);

        if (! empty($payload['code']) && $payload['code'] !== $package->code) {
            $this->ensureUniqueCode($payload['code'], excludeId: $package->id);
        }
        if (! empty($payload['name']) && $payload['name'] !== $package->name) {
            $this->ensureUniqueName($payload['name'], excludeId: $package->id);
        }

        $totals = $this->computeTotals($items, $payload['package_price'] ?? (float) $package->package_price);
        $payload = array_merge($payload, $totals);

        $newStatus = $payload['status'] ?? $package->status;
        $this->validateStatusRequirements($newStatus, $items, array_merge($package->toArray(), $payload));

        return DB::transaction(function () use ($package, $payload, $items, $data, $actor) {
            $oldStatus = $package->status;
            $payload['updated_by'] = $actor?->id;
            $package->fill($payload)->save();

            $this->syncItems($package, $items);

            ServicePackageHistory::create([
                'package_id' => $package->id,
                'action' => 'updated',
                'payload' => [
                    'changed_keys' => array_keys($payload),
                    'item_count' => count($items),
                ],
                'reason' => $data['change_reason'] ?? null,
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);

            if (! empty($payload['status']) && $payload['status'] !== $oldStatus) {
                ServicePackageHistory::create([
                    'package_id' => $package->id,
                    'action' => 'status_changed',
                    'payload' => ['from' => $oldStatus, 'to' => $payload['status']],
                    'reason' => $data['status_reason'] ?? null,
                    'changed_by' => $actor?->id,
                    'created_at' => now(),
                ]);
            }

            $this->auditLog->log($actor, 'service_package.updated', [
                'package_id' => $package->id,
                'code' => $package->code,
            ]);

            return $package->fresh(['items.service']);
        });
    }

    public function changeStatus(int $id, string $newStatus, ?string $reason, ?User $actor): ServicePackage
    {
        if (! in_array($newStatus, ServicePackage::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai khong hop le (E10).',
            ]);
        }

        $package = ServicePackage::with('items.service')->findOrFail($id);
        $oldStatus = $package->status;
        if ($oldStatus === $newStatus) {
            return $package;
        }

        $items = $package->items->map(fn ($i) => [
            'service_id' => $i->service_id,
            'quantity' => $i->quantity,
            'unit_price' => (float) $i->unit_price,
            'service' => $i->service,
        ])->all();

        $this->validateStatusRequirements($newStatus, $items, $package->toArray());

        return DB::transaction(function () use ($package, $oldStatus, $newStatus, $reason, $actor) {
            $package->status = $newStatus;
            $package->updated_by = $actor?->id;
            $package->save();

            ServicePackageHistory::create([
                'package_id' => $package->id,
                'action' => 'status_changed',
                'payload' => ['from' => $oldStatus, 'to' => $newStatus],
                'reason' => $reason,
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $this->auditLog->log($actor, 'service_package.status_changed', [
                'package_id' => $package->id,
                'code' => $package->code,
                'from' => $oldStatus,
                'to' => $newStatus,
                'reason' => $reason,
            ]);

            return $package->fresh(['items.service']);
        });
    }

    public function deletePackage(int $id, ?User $actor): void
    {
        $package = ServicePackage::findOrFail($id);

        if ($this->packageHasTransactions($package->id)) {
            throw ValidationException::withMessages([
                'package_id' => 'Goi da phat sinh giao dich, khong the xoa. Vui long Tam an / Ngung ap dung (E7).',
            ]);
        }

        DB::transaction(function () use ($package, $actor) {
            $this->auditLog->log($actor, 'service_package.deleted', [
                'package_id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
            ]);
            $package->delete();
        });
    }

    public function clonePackage(int $id, array $overrides, ?User $actor): ServicePackage
    {
        $original = ServicePackage::with('items')->findOrFail($id);

        $cloneCode = $overrides['code'] ?? $this->generateCloneCode($original->code);
        $cloneName = $overrides['name'] ?? ($original->name.' (Sao chep)');

        $this->ensureUniqueCode($cloneCode);
        $this->ensureUniqueName($cloneName);

        return DB::transaction(function () use ($original, $cloneCode, $cloneName, $overrides, $actor) {
            $clone = $original->replicate(['id', 'created_at', 'updated_at', 'created_by', 'updated_by']);
            $clone->code = $cloneCode;
            $clone->name = $cloneName;
            $clone->slug = Str::slug($cloneName);
            $clone->status = ServicePackage::STATUS_DRAFT;
            $clone->version_number = 1;
            $clone->parent_package_id = $original->id;
            $clone->created_by = $actor?->id;
            $clone->updated_by = $actor?->id;
            $clone->save();

            foreach ($original->items as $item) {
                ServicePackageItem::create([
                    'package_id' => $clone->id,
                    'service_id' => $item->service_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'note' => $item->note,
                    'display_order' => $item->display_order,
                ]);
            }

            ServicePackageHistory::create([
                'package_id' => $clone->id,
                'action' => 'cloned',
                'payload' => [
                    'from_package_id' => $original->id,
                    'from_code' => $original->code,
                ],
                'reason' => $overrides['reason'] ?? null,
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $this->auditLog->log($actor, 'service_package.cloned', [
                'package_id' => $clone->id,
                'from_package_id' => $original->id,
            ]);

            return $clone->fresh(['items.service', 'parent:id,code,name']);
        });
    }

    public function createNewVersion(int $id, array $data, ?User $actor): ServicePackage
    {
        $current = ServicePackage::with('items')->findOrFail($id);

        return DB::transaction(function () use ($current, $data, $actor) {
            $snapshot = [
                'package' => $current->only([
                    'code', 'name', 'description', 'image_path', 'status', 'visibility',
                    'original_price', 'package_price', 'discount_amount', 'discount_percent',
                    'effective_from', 'effective_to', 'usage_validity_days', 'conditions', 'notes',
                ]),
                'items' => $current->items->map(fn ($i) => [
                    'service_id' => $i->service_id,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'note' => $i->note,
                ])->all(),
            ];

            $nextVersion = $current->version_number + 1;

            ServicePackageVersion::create([
                'package_id' => $current->id,
                'version_number' => $current->version_number,
                'snapshot' => $snapshot,
                'reason' => $data['reason'] ?? null,
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $current->version_number = $nextVersion;
            $current->save();

            ServicePackageHistory::create([
                'package_id' => $current->id,
                'action' => 'version_created',
                'payload' => ['new_version' => $nextVersion],
                'reason' => $data['reason'] ?? null,
                'changed_by' => $actor?->id,
                'created_at' => now(),
            ]);

            $this->auditLog->log($actor, 'service_package.version_created', [
                'package_id' => $current->id,
                'new_version' => $nextVersion,
            ]);

            return $current->fresh(['items.service', 'versions']);
        });
    }

    public function discontinuedServiceWarning(int $packageId): array
    {
        $package = ServicePackage::with('items.service')->findOrFail($packageId);

        $warnings = [];
        foreach ($package->items as $item) {
            $service = $item->service;
            if (! $service) {
                $warnings[] = [
                    'service_id' => $item->service_id,
                    'reason' => 'missing',
                ];
                continue;
            }
            if (in_array($service->status, [Service::STATUS_HIDDEN, Service::STATUS_DISCONTINUED], true)) {
                $warnings[] = [
                    'service_id' => $service->id,
                    'service_code' => $service->service_code,
                    'name' => $service->name,
                    'status' => $service->status,
                    'reason' => 'inactive_service',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Hook for the future sales / appointments module — checks whether the
     * package has any associated transactions. Currently always false.
     */
    public function packageHasTransactions(int $packageId): bool
    {
        return false;
    }

    public function recentAuditLogs(int $limit = 30): array
    {
        return ServicePackageHistory::with('changer:id,name', 'package:id,code,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function syncItems(ServicePackage $package, array $items): void
    {
        $package->items()->delete();
        foreach ($items as $index => $item) {
            ServicePackageItem::create([
                'package_id' => $package->id,
                'service_id' => $item['service_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'note' => $item['note'] ?? null,
                'display_order' => $item['display_order'] ?? $index,
            ]);
        }
    }

    private function sanitizePayload(array $data, bool $isUpdate, ?ServicePackage $current = null): array
    {
        $payload = collect($data)->only([
            'code', 'name', 'description', 'image_path', 'status', 'visibility',
            'package_price', 'effective_from', 'effective_to',
            'usage_validity_days', 'conditions', 'notes',
        ])->all();

        if (! $isUpdate) {
            $payload['status'] = $payload['status'] ?? ServicePackage::STATUS_DRAFT;
            $payload['visibility'] = $payload['visibility'] ?? ServicePackage::VISIBILITY_INTERNAL;
            $payload['code'] = ! empty($payload['code'])
                ? trim($payload['code'])
                : $this->generateNextCode();
            if (empty($payload['code'])) {
                throw ValidationException::withMessages(['code' => 'Khong the sinh ma goi dich vu (E10).']);
            }
        }

        if (array_key_exists('name', $payload) && is_string($payload['name'])) {
            $payload['slug'] = Str::slug($payload['name']);
        }

        if (array_key_exists('package_price', $payload)) {
            $payload['package_price'] = round((float) $payload['package_price'], 2);
            if ($payload['package_price'] < 0) {
                throw ValidationException::withMessages([
                    'package_price' => 'Gia goi khong duoc nho hon 0 (E4).',
                ]);
            }
        }

        return $payload;
    }

    /**
     * @return array<int, array{service_id:int, quantity:int, unit_price:float, note:?string, display_order:int}>
     */
    private function validateAndPrepareItems(array $rawItems): array
    {
        if (empty($rawItems)) {
            throw ValidationException::withMessages([
                'items' => 'Goi phai co it nhat 1 dich vu thanh phan (E2).',
            ]);
        }

        $cleaned = [];
        $seen = [];
        foreach ($rawItems as $index => $row) {
            $serviceId = (int) ($row['service_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 1);
            if ($serviceId <= 0) {
                throw ValidationException::withMessages([
                    "items.$index.service_id" => 'Dich vu khong hop le.',
                ]);
            }
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'So luong phai >= 1.',
                ]);
            }
            if (isset($seen[$serviceId])) {
                throw ValidationException::withMessages([
                    "items.$index.service_id" => 'Dich vu bi trung trong gói.',
                ]);
            }
            $seen[$serviceId] = true;

            $cleaned[] = [
                'service_id' => $serviceId,
                'quantity' => $quantity,
                'unit_price' => isset($row['unit_price']) ? (float) $row['unit_price'] : null,
                'note' => $row['note'] ?? null,
                'display_order' => isset($row['display_order']) ? (int) $row['display_order'] : $index,
            ];
        }

        $serviceIds = array_column($cleaned, 'service_id');
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        foreach ($cleaned as $i => &$item) {
            $service = $services->get($item['service_id']);
            if (! $service) {
                throw ValidationException::withMessages([
                    "items.$i.service_id" => 'Dich vu khong ton tai (E5).',
                ]);
            }
            if (in_array($service->status, [Service::STATUS_DISCONTINUED], true)) {
                throw ValidationException::withMessages([
                    "items.$i.service_id" => "Dich vu '{$service->name}' da Ngung ap dung, khong the them vao goi (E6).",
                ]);
            }
            if ($item['unit_price'] === null) {
                $item['unit_price'] = (float) $service->price;
            }
            if ($item['unit_price'] < 0) {
                throw ValidationException::withMessages([
                    "items.$i.unit_price" => 'Gia don vi khong duoc am.',
                ]);
            }
        }
        unset($item);

        return $cleaned;
    }

    private function validateDateRange(?string $from, ?string $to): void
    {
        if ($from && $to) {
            $fromDate = Carbon::parse($from);
            $toDate = Carbon::parse($to);
            if ($fromDate->gt($toDate)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Thoi gian hieu luc khong hop le: from > to (E8).',
                ]);
            }
        }
    }

    private function validateUsageValidity(?int $days): void
    {
        if ($days !== null && $days < 0) {
            throw ValidationException::withMessages([
                'usage_validity_days' => 'Thoi han su dung sau mua khong hop le (E9).',
            ]);
        }
    }

    private function ensureUniqueCode(string $code, ?int $excludeId = null): void
    {
        $query = ServicePackage::where('code', $code);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Ma goi da ton tai (E1).',
            ]);
        }
    }

    private function ensureUniqueName(string $name, ?int $excludeId = null): void
    {
        $query = ServicePackage::where('name', $name);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Ten goi da ton tai (E1).',
            ]);
        }
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     * @return array{original_price:float, discount_amount:float, discount_percent:float}
     */
    private function computeTotals(array $items, float $packagePrice): array
    {
        $original = 0.0;
        foreach ($items as $item) {
            $original += ((float) $item['unit_price']) * (int) $item['quantity'];
        }
        $original = round($original, 2);
        $packagePrice = round($packagePrice, 2);

        // E3: package price must not exceed sum of items
        if ($packagePrice > $original && $original > 0) {
            throw ValidationException::withMessages([
                'package_price' => 'Gia goi khong duoc lon hon tong gia dich vu thanh phan (E3).',
            ]);
        }

        $discount = max(0, $original - $packagePrice);
        $percent = $original > 0 ? round(($discount / $original) * 100, 2) : 0.0;

        return [
            'original_price' => $original,
            'discount_amount' => $discount,
            'discount_percent' => $percent,
        ];
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     */
    private function validateStatusRequirements(string $newStatus, array $items, array $payload): void
    {
        if (! in_array($newStatus, ServicePackage::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Trang thai khong hop le (E10).',
            ]);
        }

        if ($newStatus === ServicePackage::STATUS_ACTIVE) {
            if (empty($items)) {
                throw ValidationException::withMessages([
                    'items' => 'Goi phai co it nhat 1 dich vu thanh phan truoc khi Dang ap dung (E2).',
                ]);
            }
            $packagePrice = (float) ($payload['package_price'] ?? 0);
            if ($packagePrice <= 0) {
                throw ValidationException::withMessages([
                    'package_price' => 'Gia goi phai > 0 truoc khi Dang ap dung (E4).',
                ]);
            }
            $original = (float) ($payload['original_price'] ?? 0);
            if ($packagePrice > $original && $original > 0) {
                throw ValidationException::withMessages([
                    'package_price' => 'Gia goi khong duoc lon hon tong gia thanh phan (E3).',
                ]);
            }

            // E6: cannot activate if any component service is discontinued/hidden.
            foreach ($items as $i => $item) {
                $service = $item['service']
                    ?? Service::find($item['service_id']);
                if (! $service) {
                    continue;
                }
                if (in_array($service->status, [Service::STATUS_DISCONTINUED, Service::STATUS_HIDDEN], true)) {
                    throw ValidationException::withMessages([
                        "items.$i.service_id" => "Khong the Dang ap dung khi dich vu '{$service->name}' dang Tam an/Ngung ap dung (E6).",
                    ]);
                }
            }

            // E8: range validity
            if (! empty($payload['effective_from']) && ! empty($payload['effective_to'])) {
                $this->validateDateRange($payload['effective_from'], $payload['effective_to']);
            }
        }
    }

    private function generateNextCode(): string
    {
        $existing = ServicePackage::where('code', 'like', 'PKG%')->pluck('code');
        $max = 0;
        foreach ($existing as $code) {
            if (preg_match('/^PKG(\d+)$/', $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'PKG'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function generateCloneCode(string $base): string
    {
        $i = 1;
        do {
            $candidate = substr($base, 0, 26).'_C'.$i;
            if (! ServicePackage::where('code', $candidate)->exists()) {
                return $candidate;
            }
            $i++;
        } while ($i < 1000);

        throw ValidationException::withMessages([
            'code' => 'Khong the sinh ma goi nhan ban.',
        ]);
    }
}
