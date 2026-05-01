<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServicePriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicePriceController extends Controller
{
    public function __construct(private readonly ServicePriceService $prices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'service_group_id', 'status', 'only', 'per_page']);

        return response()->json($this->prices->listGroupedByService($filters));
    }

    public function timeline(Request $request, int $serviceId): JsonResponse
    {
        return response()->json($this->prices->timelineForService($serviceId));
    }

    public function pending(): JsonResponse
    {
        return response()->json($this->prices->listPendingProposals());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateForm($request);

        $user = $request->user();
        $role = $user?->roles->first()?->slug;
        $isProposal = ($data['mode'] ?? 'direct') === 'proposal' || $role === 'ke_toan';
        // Admin override: when admin posts mode=direct, do NOT mark as proposal even if posted by ke_toan
        if ($role === 'admin' && ($data['mode'] ?? 'direct') === 'direct') {
            $isProposal = false;
        }

        $record = $this->prices->createPrice(
            (int) $data['service_id'],
            $data,
            $user,
            isProposal: $isProposal
        );

        return response()->json($record, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validateForm($request, requireServiceId: false);
        $record = $this->prices->updatePrice($id, $data, $request->user());

        return response()->json($record);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->prices->deletePrice($id, $request->user());

        return response()->json(['message' => 'Da xoa ban ghi gia.']);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $record = $this->prices->approveProposal($id, $request->user());

        return response()->json($record);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $record = $this->prices->rejectProposal($id, $data['reason'] ?? null, $request->user());

        return response()->json($record);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json($this->prices->recentAuditLogs());
    }

    /**
     * @return array<string,mixed>
     */
    private function validateForm(Request $request, bool $requireServiceId = true): array
    {
        $rules = [
            'service_id' => $requireServiceId ? 'required|integer|exists:services,id' : 'nullable|integer|exists:services,id',
            'price' => 'required|numeric|min:0.01',
            'apply_now' => 'nullable|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'reason' => 'nullable|string|max:255',
            'mode' => 'nullable|string|in:direct,proposal',
        ];

        $data = $request->validate($rules);

        if (empty($data['effective_from']) && empty($data['apply_now'])) {
            $data['apply_now'] = true;
        }

        if (! empty($data['apply_now']) && empty($data['effective_from'])) {
            $data['effective_from'] = now()->toDateString();
        }

        return $data;
    }
}
