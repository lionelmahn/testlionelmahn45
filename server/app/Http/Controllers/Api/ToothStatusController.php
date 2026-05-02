<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ToothStatusGroup;
use App\Models\ToothStatusProposal;
use App\Services\ToothStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ToothStatusController extends Controller
{
    public function __construct(private readonly ToothStatusService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'group_id', 'is_active', 'per_page']);

        return response()->json($this->service->listStatuses($filters));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->findStatus($id));
    }

    public function groups(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);

        return response()->json($this->service->listGroups($activeOnly ? true : null));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateBody($request, isCreate: true);
        $status = $this->service->createStatus($data, $request->user());

        return response()->json($status, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validateBody($request, isCreate: false);
        $status = $this->service->updateStatus($id, $data, $request->user());

        return response()->json($status);
    }

    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
            'note' => 'nullable|string|max:500',
        ]);

        $status = $this->service->setActive(
            $id,
            (bool) $data['is_active'],
            $data['note'] ?? null,
            $request->user(),
        );

        return response()->json($status);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->deleteStatus($id, $request->user());

        return response()->json(['message' => 'Da xoa trang thai rang.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|min:1',
        ]);

        return response()->json($this->service->reorder($data['ordered_ids'], $request->user()));
    }

    public function history(int $id): JsonResponse
    {
        return response()->json($this->service->statusHistory($id));
    }

    public function recentHistory(): JsonResponse
    {
        return response()->json($this->service->recentHistory());
    }

    public function listProposals(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'per_page']);

        return response()->json([
            'items' => $this->service->listProposals($filters),
            'pending_count' => $this->service->pendingProposalCount(),
        ]);
    }

    public function storeProposal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([
                ToothStatusProposal::ACTION_CREATE,
                ToothStatusProposal::ACTION_UPDATE,
            ])],
            'tooth_status_id' => 'nullable|integer|exists:tooth_statuses,id',
            'payload' => 'required|array',
            'payload.code' => 'nullable|string|max:30',
            'payload.name' => 'nullable|string|max:255',
            'payload.tooth_status_group_id' => 'nullable|integer|exists:tooth_status_groups,id',
            'payload.color' => 'nullable|string|max:16',
            'payload.icon' => 'nullable|string|max:32',
            'payload.description' => 'nullable|string|max:500',
            'payload.notes' => 'nullable|string|max:500',
            'payload.is_active' => 'nullable|boolean',
        ]);

        $proposal = $this->service->createProposal($data, $request->user());

        return response()->json($proposal, 201);
    }

    public function approveProposal(Request $request, int $id): JsonResponse
    {
        return response()->json($this->service->approveProposal($id, $request->user()));
    }

    public function rejectProposal(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->service->rejectProposal($id, $data['note'] ?? null, $request->user())
        );
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:tooth_status_groups,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['display_order'] = $data['display_order']
            ?? ((int) ToothStatusGroup::max('display_order') + 1);
        $data['is_active'] = $data['is_active'] ?? true;

        $group = ToothStatusGroup::create($data);

        return response()->json($group, 201);
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        $group = ToothStatusGroup::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $group->fill($data)->save();

        return response()->json($group);
    }

    private function validateBody(Request $request, bool $isCreate): array
    {
        $codeRule = ['nullable', 'string', 'max:30'];
        if ($isCreate) {
            $codeRule = ['required', 'string', 'max:30', Rule::unique('tooth_statuses', 'code')];
        }

        $rules = [
            'code' => $codeRule,
            'name' => $isCreate ? 'required|string|max:255' : 'sometimes|string|max:255',
            'tooth_status_group_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:tooth_status_groups,id'],
            'color' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:16'],
            'icon' => 'nullable|string|max:32',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];

        return $request->validate($rules);
    }
}
