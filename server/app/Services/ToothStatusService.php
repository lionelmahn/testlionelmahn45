<?php

namespace App\Services;

use App\Models\ToothStatus;
use App\Models\ToothStatusGroup;
use App\Models\ToothStatusHistory;
use App\Models\ToothStatusProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * UC4.4 — Quan ly trang thai rang.
 *
 * Master data CRUD with admin-only mutations, doctor-side propose flow (A1),
 * drag-drop reorder, and per-record audit history for the "Lich su thay doi"
 * card.
 */
class ToothStatusService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function listStatuses(array $filters): LengthAwarePaginator
    {
        $query = ToothStatus::query()
            ->with(['group', 'creator:id,name', 'updater:id,name']);

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('name', 'like', $term);
            });
        }

        if (! empty($filters['group_id']) && $filters['group_id'] !== 'all') {
            $query->where('tooth_status_group_id', $filters['group_id']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== 'all' && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        if ($perPage < 1) {
            $perPage = 20;
        }

        return $query
            ->orderBy('display_order')
            ->orderBy('id')
            ->paginate(min($perPage, 100));
    }

    public function findStatus(int $id): array
    {
        $status = ToothStatus::with(['group', 'creator:id,name', 'updater:id,name'])->findOrFail($id);

        return [
            'status' => $status,
            'usage' => $this->usageInfo($status),
        ];
    }

    public function listGroups(?bool $activeOnly = null): Collection
    {
        $query = ToothStatusGroup::query()->orderBy('display_order')->orderBy('name');

        if ($activeOnly === true) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    public function createStatus(array $data, ?User $actor): ToothStatus
    {
        $payload = $this->validatePayload($data, isUpdate: false);
        $this->ensureUniqueCode($payload['code']);
        $this->ensureUniqueNameWithinGroup($payload['name'], $payload['tooth_status_group_id']);

        return DB::transaction(function () use ($payload, $actor) {
            $payload['display_order'] = $payload['display_order']
                ?? ((int) ToothStatus::max('display_order') + 1);
            $payload['created_by'] = $actor?->id;
            $payload['updated_by'] = $actor?->id;

            $status = ToothStatus::create($payload);

            $this->recordHistory($status, 'created', null, $status->fresh()->toArray(), $actor, 'Tao moi');
            $this->auditLog->log($actor, 'tooth_status.created', [
                'tooth_status_id' => $status->id,
                'code' => $status->code,
                'name' => $status->name,
            ]);

            return $status->fresh(['group']);
        });
    }

    public function updateStatus(int $id, array $data, ?User $actor): ToothStatus
    {
        $status = ToothStatus::findOrFail($id);
        $payload = $this->validatePayload($data, isUpdate: true);

        // Code is immutable on update (matches UI: input disabled + business rule "ma duy nhat").
        unset($payload['code']);

        if (! empty($payload['name']) || array_key_exists('tooth_status_group_id', $payload)) {
            $this->ensureUniqueNameWithinGroup(
                $payload['name'] ?? $status->name,
                $payload['tooth_status_group_id'] ?? $status->tooth_status_group_id,
                excludeId: $status->id,
            );
        }

        return DB::transaction(function () use ($status, $payload, $actor) {
            $before = $status->getOriginal();
            $payload['updated_by'] = $actor?->id;
            $status->fill($payload)->save();
            $after = $status->fresh()->toArray();

            $this->recordHistory($status, 'updated', $before, $after, $actor, 'Cap nhat thong tin');
            $this->auditLog->log($actor, 'tooth_status.updated', [
                'tooth_status_id' => $status->id,
                'changes' => array_keys($payload),
            ]);

            return $status->fresh(['group']);
        });
    }

    public function setActive(int $id, bool $isActive, ?string $note, ?User $actor): ToothStatus
    {
        $status = ToothStatus::findOrFail($id);
        if ($status->is_active === $isActive) {
            return $status;
        }

        return DB::transaction(function () use ($status, $isActive, $note, $actor) {
            $before = ['is_active' => $status->is_active];
            $status->is_active = $isActive;
            $status->updated_by = $actor?->id;
            $status->save();

            $action = $isActive ? 'activated' : 'deactivated';
            $this->recordHistory($status, $action, $before, ['is_active' => $isActive], $actor, $note);
            $this->auditLog->log($actor, 'tooth_status.'.$action, [
                'tooth_status_id' => $status->id,
                'is_active' => $isActive,
            ]);

            return $status->fresh(['group']);
        });
    }

    public function deleteStatus(int $id, ?User $actor): void
    {
        $status = ToothStatus::findOrFail($id);

        $usage = $this->usageInfo($status);
        if (($usage['used_in_records'] ?? 0) > 0) {
            // E3 — khong xoa trang thai da dung trong ho so benh nhan.
            throw ValidationException::withMessages([
                'tooth_status' => 'Trang thai da duoc su dung trong ho so benh nhan, khong the xoa (E3).',
            ]);
        }

        DB::transaction(function () use ($status, $actor) {
            $before = $status->toArray();
            $this->recordHistory($status, 'deleted', $before, null, $actor, 'Xoa trang thai');

            // Detach history from cascading-null FK by recording before delete.
            $this->auditLog->log($actor, 'tooth_status.deleted', [
                'tooth_status_id' => $status->id,
                'code' => $status->code,
            ]);
            $status->delete();
        });
    }

    /**
     * Reorder by ordered ids — admin drag-and-drop.
     *
     * @param  int[]  $orderedIds
     */
    public function reorder(array $orderedIds, ?User $actor): Collection
    {
        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds), fn ($id) => $id > 0));
        if (empty($orderedIds)) {
            return collect();
        }

        DB::transaction(function () use ($orderedIds, $actor) {
            foreach ($orderedIds as $index => $id) {
                ToothStatus::where('id', $id)->update([
                    'display_order' => $index + 1,
                    'updated_by' => $actor?->id,
                ]);
            }

            $this->auditLog->log($actor, 'tooth_status.reordered', [
                'count' => count($orderedIds),
                'ids' => $orderedIds,
            ]);
        });

        return ToothStatus::with('group')
            ->whereIn('id', $orderedIds)
            ->orderBy('display_order')
            ->get();
    }

    public function listProposals(array $filters): LengthAwarePaginator
    {
        $query = ToothStatusProposal::query()
            ->with(['proposer:id,name', 'reviewer:id,name', 'toothStatus:id,code,name']);

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->orderByDesc('created_at')->paginate(min($perPage, 100));
    }

    public function pendingProposalCount(): int
    {
        return ToothStatusProposal::where('status', ToothStatusProposal::STATUS_PENDING)->count();
    }

    public function createProposal(array $data, ?User $actor): ToothStatusProposal
    {
        $action = $data['action'] ?? null;
        if (! in_array($action, [ToothStatusProposal::ACTION_CREATE, ToothStatusProposal::ACTION_UPDATE], true)) {
            throw ValidationException::withMessages([
                'action' => 'Hanh dong de xuat khong hop le.',
            ]);
        }

        $payload = $data['payload'] ?? [];
        $isUpdate = $action === ToothStatusProposal::ACTION_UPDATE;
        $toothStatusId = $isUpdate ? ($data['tooth_status_id'] ?? null) : null;

        if ($isUpdate && ! $toothStatusId) {
            throw ValidationException::withMessages([
                'tooth_status_id' => 'Thieu trang thai can chinh sua.',
            ]);
        }

        // Light validation on the payload — admin re-validates on approve.
        $required = $isUpdate ? [] : ['code', 'name', 'tooth_status_group_id', 'color'];
        foreach ($required as $field) {
            if (empty($payload[$field])) {
                throw ValidationException::withMessages([
                    'payload.'.$field => 'Truong "'.$field.'" la bat buoc (E2).',
                ]);
            }
        }

        $proposal = ToothStatusProposal::create([
            'action' => $action,
            'tooth_status_id' => $toothStatusId,
            'payload' => $payload,
            'status' => ToothStatusProposal::STATUS_PENDING,
            'proposed_by' => $actor?->id,
        ]);

        if ($toothStatusId && ($status = ToothStatus::find($toothStatusId))) {
            $this->recordHistory(
                $status,
                'proposal_submitted',
                null,
                ['proposal_id' => $proposal->id, 'action' => $action],
                $actor,
                'De xuat chinh sua tu bac si',
            );
        }

        $this->auditLog->log($actor, 'tooth_status.proposal_submitted', [
            'proposal_id' => $proposal->id,
            'action' => $action,
        ]);

        return $proposal->fresh(['proposer:id,name', 'toothStatus:id,code,name']);
    }

    public function approveProposal(int $proposalId, ?User $actor): ToothStatusProposal
    {
        $proposal = ToothStatusProposal::findOrFail($proposalId);
        if ($proposal->status !== ToothStatusProposal::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'proposal' => 'De xuat da duoc xu ly truoc do.',
            ]);
        }

        return DB::transaction(function () use ($proposal, $actor) {
            $payload = (array) $proposal->payload;

            if ($proposal->action === ToothStatusProposal::ACTION_CREATE) {
                $created = $this->createStatus($payload, $actor);
                $proposal->tooth_status_id = $created->id;
            } else {
                if (! $proposal->tooth_status_id) {
                    throw ValidationException::withMessages([
                        'proposal' => 'De xuat khong gan voi trang thai cu the.',
                    ]);
                }
                $this->updateStatus($proposal->tooth_status_id, $payload, $actor);
            }

            $proposal->status = ToothStatusProposal::STATUS_APPROVED;
            $proposal->reviewed_by = $actor?->id;
            $proposal->reviewed_at = now();
            $proposal->save();

            $this->auditLog->log($actor, 'tooth_status.proposal_approved', [
                'proposal_id' => $proposal->id,
                'tooth_status_id' => $proposal->tooth_status_id,
            ]);

            return $proposal->fresh([
                'proposer:id,name',
                'reviewer:id,name',
                'toothStatus:id,code,name',
            ]);
        });
    }

    public function rejectProposal(int $proposalId, ?string $note, ?User $actor): ToothStatusProposal
    {
        $proposal = ToothStatusProposal::findOrFail($proposalId);
        if ($proposal->status !== ToothStatusProposal::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'proposal' => 'De xuat da duoc xu ly truoc do.',
            ]);
        }

        $proposal->status = ToothStatusProposal::STATUS_REJECTED;
        $proposal->reviewed_by = $actor?->id;
        $proposal->reviewed_at = now();
        $proposal->review_note = $note;
        $proposal->save();

        $this->auditLog->log($actor, 'tooth_status.proposal_rejected', [
            'proposal_id' => $proposal->id,
            'note' => $note,
        ]);

        return $proposal->fresh(['proposer:id,name', 'reviewer:id,name', 'toothStatus:id,code,name']);
    }

    public function statusHistory(int $statusId, int $limit = 50): Collection
    {
        return ToothStatusHistory::with('performer:id,name')
            ->where('tooth_status_id', $statusId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function recentHistory(int $limit = 20): Collection
    {
        return ToothStatusHistory::with(['performer:id,name', 'toothStatus:id,code,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function validatePayload(array $data, bool $isUpdate): array
    {
        $allowed = [
            'code',
            'name',
            'tooth_status_group_id',
            'color',
            'icon',
            'description',
            'notes',
            'display_order',
            'is_active',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if (! $isUpdate) {
            foreach (['code', 'name', 'tooth_status_group_id', 'color'] as $required) {
                if (empty($payload[$required])) {
                    throw ValidationException::withMessages([
                        $required => 'Thieu thong tin bat buoc (E2).',
                    ]);
                }
            }
        }

        if (! empty($payload['code'])) {
            $payload['code'] = strtoupper(trim($payload['code']));
            if (! preg_match('/^[A-Z0-9_-]{2,30}$/', $payload['code'])) {
                throw ValidationException::withMessages([
                    'code' => 'Ma trang thai chi cho phep chu hoa, so, gach noi.',
                ]);
            }
        }

        if (! empty($payload['color']) && ! $this->isValidColor($payload['color'])) {
            // E4 — mau hien thi khong hop le.
            throw ValidationException::withMessages([
                'color' => 'Mau hien thi khong hop le (E4).',
            ]);
        }

        if (! empty($payload['tooth_status_group_id'])) {
            $payload['tooth_status_group_id'] = (int) $payload['tooth_status_group_id'];
            if (! ToothStatusGroup::where('id', $payload['tooth_status_group_id'])->exists()) {
                throw ValidationException::withMessages([
                    'tooth_status_group_id' => 'Nhom trang thai khong ton tai.',
                ]);
            }
        }

        if (array_key_exists('is_active', $payload)) {
            $payload['is_active'] = filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('display_order', $payload) && $payload['display_order'] !== null && $payload['display_order'] !== '') {
            $payload['display_order'] = max(0, (int) $payload['display_order']);
        } else {
            unset($payload['display_order']);
        }

        foreach (['name', 'description', 'notes', 'icon'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = Str::limit(trim($payload[$field]), $field === 'icon' ? 32 : 500, '');
            }
        }

        return $payload;
    }

    private function isValidColor(string $color): bool
    {
        // Accept hex (#fff, #ffffff) or simple bg-* tailwind-style tokens.
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return true;
        }
        if (preg_match('/^[a-z0-9-]{1,30}$/', $color)) {
            return true;
        }

        return false;
    }

    private function ensureUniqueCode(string $code, ?int $excludeId = null): void
    {
        $query = ToothStatus::where('code', $code);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            // E1 — ma da ton tai.
            throw ValidationException::withMessages([
                'code' => 'Ma trang thai da ton tai (E1).',
            ]);
        }
    }

    private function ensureUniqueNameWithinGroup(string $name, ?int $groupId, ?int $excludeId = null): void
    {
        $query = ToothStatus::where('name', $name);
        if ($groupId) {
            $query->where('tooth_status_group_id', $groupId);
        }
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Ten trang thai da ton tai trong nhom.',
            ]);
        }
    }

    /**
     * Inspect downstream usage of a tooth status. Most schemas storing
     * patient-facing tooth records are out of scope for UC4.4 yet, so we
     * defensively check well-known table/column combinations and return zero
     * when none are present. The "linked services" count is a placeholder
     * until a `service_tooth_status` pivot lands.
     */
    public function usageInfo(ToothStatus $status): array
    {
        $usedInRecords = 0;

        $candidates = [
            ['tooth_records', 'tooth_status_id'],
            ['dental_records', 'tooth_status_id'],
            ['patient_tooth_statuses', 'tooth_status_id'],
        ];
        foreach ($candidates as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $usedInRecords += DB::table($table)->where($column, $status->id)->count();
            }
        }

        $linkedServices = 0;
        if (Schema::hasTable('service_tooth_status') && Schema::hasColumn('service_tooth_status', 'tooth_status_id')) {
            $linkedServices = DB::table('service_tooth_status')->where('tooth_status_id', $status->id)->count();
        }

        return [
            'used_in_records' => $usedInRecords,
            'linked_services' => $linkedServices,
        ];
    }

    private function recordHistory(
        ToothStatus $status,
        string $action,
        ?array $before,
        ?array $after,
        ?User $actor,
        ?string $note = null,
    ): void {
        ToothStatusHistory::create([
            'tooth_status_id' => $status->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'note' => $note,
            'performed_by' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
