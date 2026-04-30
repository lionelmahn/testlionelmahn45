<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use App\Services\ServicePackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicePackageController extends Controller
{
    public function __construct(private readonly ServicePackageService $packages)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'status', 'visibility', 'effective_from', 'effective_to', 'per_page',
        ]);

        $user = $request->user();
        $role = $user?->roles->first()?->slug ?? null;
        if ($role === 'benh_nhan') {
            $filters['visibility'] = 'public';
            $filters['status'] = ServicePackage::STATUS_ACTIVE;
        }

        return response()->json($this->packages->listPackages($filters));
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'effective_from', 'effective_to', 'per_page']);

        return response()->json($this->packages->publicListPackages($filters));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $package = $this->packages->findPackage($id);

        $role = $request->user()?->roles->first()?->slug ?? null;
        if ($role === 'benh_nhan'
            && ($package->visibility !== ServicePackage::VISIBILITY_PUBLIC
                || $package->status !== ServicePackage::STATUS_ACTIVE)
        ) {
            return response()->json(['message' => 'Khong tim thay goi dich vu.'], 404);
        }

        return response()->json($package);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateForm($request, isCreate: true);
        $package = $this->packages->createPackage($data, $request->user());

        return response()->json($package, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validateForm($request, isCreate: false);
        $package = $this->packages->updatePackage($id, $data, $request->user());

        return response()->json($package);
    }

    public function changeStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(ServicePackage::STATUSES)],
            'reason' => 'nullable|string|max:500',
        ]);

        $package = $this->packages->changeStatus($id, $data['status'], $data['reason'] ?? null, $request->user());

        return response()->json($package);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->packages->deletePackage($id, $request->user());

        return response()->json(['message' => 'Da xoa goi dich vu.']);
    }

    public function clone(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'code' => 'nullable|string|max:30',
            'name' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:500',
        ]);

        $clone = $this->packages->clonePackage($id, $data, $request->user());

        return response()->json($clone, 201);
    }

    public function newVersion(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $package = $this->packages->createNewVersion($id, $data, $request->user());

        return response()->json($package);
    }

    public function discontinuedWarnings(int $id): JsonResponse
    {
        return response()->json($this->packages->discontinuedServiceWarning($id));
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json($this->packages->recentAuditLogs());
    }

    private function validateForm(Request $request, bool $isCreate): array
    {
        $codeRule = ['nullable', 'string', 'max:30'];
        if ($isCreate) {
            $codeRule[] = Rule::unique('service_packages', 'code');
        }

        return $request->validate([
            'code' => $codeRule,
            'name' => $isCreate ? 'required|string|max:255' : 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'image_path' => 'nullable|string|max:500',
            'status' => ['nullable', 'string', Rule::in(ServicePackage::STATUSES)],
            'visibility' => ['nullable', 'string', Rule::in(ServicePackage::VISIBILITIES)],
            'package_price' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'usage_validity_days' => 'nullable|integer|min:0',
            'conditions' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'items' => $isCreate ? 'required|array|min:1' : 'sometimes|array',
            'items.*.service_id' => 'required|integer|exists:services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.note' => 'nullable|string|max:255',
            'items.*.display_order' => 'nullable|integer',
            'change_reason' => 'nullable|string|max:500',
            'status_reason' => 'nullable|string|max:500',
        ]);
    }
}
