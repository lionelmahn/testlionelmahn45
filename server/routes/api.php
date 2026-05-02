<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\MyProfessionalProfileController;
use App\Http\Controllers\Api\MyWorkScheduleController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\ProfessionalProfileController;
use App\Http\Controllers\Api\ServiceAttachmentController;
use App\Http\Controllers\Api\ServiceCatalogController;
use App\Http\Controllers\Api\ServicePackageController;
use App\Http\Controllers\Api\ServicePriceController;
use App\Http\Controllers\Api\ShiftSwapRequestController;
use App\Http\Controllers\Api\ToothStatusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\WorkScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/public/services', [ServiceCatalogController::class, 'publicIndex']);
Route::get('/public/service-groups', [ServiceCatalogController::class, 'groups']);
Route::get('/public/service-packages', [ServicePackageController::class, 'publicIndex']);
Route::post('/verify-login-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

Route::post('/password/forgot/send-otp', [PasswordResetController::class, 'sendResetOtp']);
Route::post('/password/forgot/verify-otp', [PasswordResetController::class, 'verifyResetOtp']);
Route::post('/password/forgot/reset', [PasswordResetController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return array_merge($user->toArray(), [
            'role' => $user->roles->first()?->slug ?? '',
            'permission_slugs' => $user->getPermissionSlugs()
        ]);
    });

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/roles/{id}/permissions', [PermissionController::class, 'getRolePermissions']);
    Route::put('/roles/{id}/permissions', [PermissionController::class, 'updateRolePermissions']);
    Route::get('/users/{id}/permissions', [PermissionController::class, 'getUserPermissions']);
    Route::put('/users/{id}/permissions', [PermissionController::class, 'updateUserPermissions']);
    Route::get('/my-professional-profile', [MyProfessionalProfileController::class, 'show']);
    Route::put('/my-professional-profile/{professionalProfile}', [MyProfessionalProfileController::class, 'update'])->whereNumber('professionalProfile');
    Route::post('/my-professional-profile/{professionalProfile}/submit', [MyProfessionalProfileController::class, 'submit'])->whereNumber('professionalProfile');

    // Lich lam viec - cho moi user da dang nhap
    Route::get('/my-work-schedule', [MyWorkScheduleController::class, 'index']);
    Route::get('/staff-lookup', [MyWorkScheduleController::class, 'staffLookup']);
    Route::get('/work-shift-templates', [WorkScheduleController::class, 'templates']);
    Route::post('/work-schedules/{schedule}/leave-request', [LeaveRequestController::class, 'store'])->whereNumber('schedule');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store']);
    Route::post('/shift-swap-requests', [ShiftSwapRequestController::class, 'store']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user');
        Route::put('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->whereNumber('user');
        Route::post('/users/{user}/send-reset-otp', [UserController::class, 'sendResetOtp'])->whereNumber('user');
        Route::post('/users/{user}/verify-reset', [UserController::class, 'verifyAndResetPassword'])->whereNumber('user');
        Route::get('/users/history', [UserController::class, 'getHistory']);

        // Staff Routes
        Route::get('/staff', [\App\Http\Controllers\Api\StaffController::class, 'index']);
        Route::post('/staff', [\App\Http\Controllers\Api\StaffController::class, 'store']);
        Route::get('/staff/{staff}', [\App\Http\Controllers\Api\StaffController::class, 'show'])->whereNumber('staff');
        Route::put('/staff/{staff}', [\App\Http\Controllers\Api\StaffController::class, 'update'])->whereNumber('staff');
        Route::put('/staff/{staff}/status', [\App\Http\Controllers\Api\StaffController::class, 'changeStatus'])->whereNumber('staff');
        Route::get('/staff/{staff}/history', [\App\Http\Controllers\Api\StaffController::class, 'history'])->whereNumber('staff');
        Route::post('/staff/{staff}/reset-password', [\App\Http\Controllers\Api\StaffController::class, 'resetPassword'])->whereNumber('staff');
        Route::get('/branches', [\App\Http\Controllers\Api\BranchController::class, 'index']);
        Route::get('/professional-profiles/options', [ProfessionalProfileController::class, 'options']);
        Route::get('/professional-profiles', [ProfessionalProfileController::class, 'index']);
        Route::post('/professional-profiles', [ProfessionalProfileController::class, 'store']);
        Route::get('/professional-profiles/{professionalProfile}', [ProfessionalProfileController::class, 'show'])->whereNumber('professionalProfile');
        Route::post('/professional-profiles/{professionalProfile}', [ProfessionalProfileController::class, 'update'])->whereNumber('professionalProfile');
        Route::post('/professional-profiles/{professionalProfile}/submit', [ProfessionalProfileController::class, 'submit'])->whereNumber('professionalProfile');
        Route::post('/professional-profiles/{professionalProfile}/approve', [ProfessionalProfileController::class, 'approve'])->whereNumber('professionalProfile');
        Route::post('/professional-profiles/{professionalProfile}/reject', [ProfessionalProfileController::class, 'reject'])->whereNumber('professionalProfile');
        Route::post('/professional-profiles/{professionalProfile}/invalidate', [ProfessionalProfileController::class, 'invalidate'])->whereNumber('professionalProfile');
        Route::get('/professional-profiles/{professionalProfile}/history', [ProfessionalProfileController::class, 'history'])->whereNumber('professionalProfile');

        Route::get('/roles', [UserController::class, 'getAllRoles']);
        Route::get('/admin/dashboard-stats', [DashboardController::class, 'getAdminStats']);

        // Service Catalog (UC4.1)
        Route::post('/services', [ServiceCatalogController::class, 'store']);
        Route::put('/services/{service}', [ServiceCatalogController::class, 'update'])->whereNumber('service');
        Route::post('/services/{service}/status', [ServiceCatalogController::class, 'changeStatus'])->whereNumber('service');
        Route::delete('/services/{service}', [ServiceCatalogController::class, 'destroy'])->whereNumber('service');
        Route::post('/services/{service}/attachments', [ServiceAttachmentController::class, 'store'])->whereNumber('service');
        Route::delete('/services/{service}/attachments/{attachment}', [ServiceAttachmentController::class, 'destroy'])->whereNumber('service')->whereNumber('attachment');
        Route::get('/services/audit-logs', [ServiceCatalogController::class, 'auditLogs']);

        // Work Schedule Management (UC3.3)
        Route::get('/work-schedules', [WorkScheduleController::class, 'index']);
        Route::post('/work-schedules', [WorkScheduleController::class, 'store']);
        Route::post('/work-schedules/copy', [WorkScheduleController::class, 'copy']);
        Route::get('/work-schedules/branch-stats', [WorkScheduleController::class, 'branchStats']);
        Route::get('/work-schedules/audit-logs', [WorkScheduleController::class, 'auditLogs']);
        Route::get('/work-schedules/{schedule}', [WorkScheduleController::class, 'show'])->whereNumber('schedule');
        Route::put('/work-schedules/{schedule}', [WorkScheduleController::class, 'update'])->whereNumber('schedule');
        Route::delete('/work-schedules/{schedule}', [WorkScheduleController::class, 'destroy'])->whereNumber('schedule');

        Route::get('/leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('/leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->whereNumber('leaveRequest');
        Route::post('/leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->whereNumber('leaveRequest');

        Route::get('/shift-swap-requests', [ShiftSwapRequestController::class, 'index']);
        Route::post('/shift-swap-requests/{swap}/approve', [ShiftSwapRequestController::class, 'approve'])->whereNumber('swap');
        Route::post('/shift-swap-requests/{swap}/reject', [ShiftSwapRequestController::class, 'reject'])->whereNumber('swap');

        // Service Package (UC4.2)
        Route::post('/service-packages', [ServicePackageController::class, 'store']);
        Route::put('/service-packages/{package}', [ServicePackageController::class, 'update'])->whereNumber('package');
        Route::post('/service-packages/{package}/status', [ServicePackageController::class, 'changeStatus'])->whereNumber('package');
        Route::post('/service-packages/{package}/clone', [ServicePackageController::class, 'clone'])->whereNumber('package');
        Route::post('/service-packages/{package}/new-version', [ServicePackageController::class, 'newVersion'])->whereNumber('package');
        Route::delete('/service-packages/{package}', [ServicePackageController::class, 'destroy'])->whereNumber('package');
        Route::get('/service-packages/audit-logs', [ServicePackageController::class, 'auditLogs']);
    });

    // Service Prices (UC4.3)
    Route::middleware('permission:prices.view')->group(function () {
        Route::get('/service-prices', [ServicePriceController::class, 'index']);
        Route::get('/service-prices/pending', [ServicePriceController::class, 'pending']);
        Route::get('/service-prices/audit-logs', [ServicePriceController::class, 'auditLogs']);
        Route::get('/service-prices/services/{service}/timeline', [ServicePriceController::class, 'timeline'])->whereNumber('service');
    });
    Route::middleware('permission:prices.create')->group(function () {
        Route::post('/service-prices', [ServicePriceController::class, 'store']);
    });
    Route::middleware('permission:prices.edit')->group(function () {
        Route::put('/service-prices/{price}', [ServicePriceController::class, 'update'])->whereNumber('price');
    });
    Route::middleware('permission:prices.delete')->group(function () {
        Route::delete('/service-prices/{price}', [ServicePriceController::class, 'destroy'])->whereNumber('price');
    });
    Route::middleware('permission:prices.approve')->group(function () {
        Route::post('/service-prices/{price}/approve', [ServicePriceController::class, 'approve'])->whereNumber('price');
        Route::post('/service-prices/{price}/reject', [ServicePriceController::class, 'reject'])->whereNumber('price');
    });

    // Service Package - read-only for any authenticated user; controller scopes for benh_nhan
    Route::get('/service-packages', [ServicePackageController::class, 'index']);
    Route::get('/service-packages/{package}', [ServicePackageController::class, 'show'])->whereNumber('package');
    Route::get('/service-packages/{package}/discontinued-warnings', [ServicePackageController::class, 'discontinuedWarnings'])->whereNumber('package');

    // Service catalog (read for any authenticated user, scope filter applied in service)
    Route::get('/services', [ServiceCatalogController::class, 'index']);
    Route::get('/services/groups', [ServiceCatalogController::class, 'groups']);
    Route::get('/services/specialties', [ServiceCatalogController::class, 'specialties']);
    Route::get('/services/{service}', [ServiceCatalogController::class, 'show'])->whereNumber('service');
    Route::get('/services/{service}/attachments', [ServiceAttachmentController::class, 'index'])->whereNumber('service');
    Route::get('/services/{service}/attachments/{attachment}/download', [ServiceAttachmentController::class, 'download'])
        ->whereNumber('service')->whereNumber('attachment');

    // Tooth Status Management (UC4.4)
    Route::middleware('permission:tooth_statuses.view')->group(function () {
        Route::get('/tooth-statuses', [ToothStatusController::class, 'index']);
        Route::get('/tooth-status-groups', [ToothStatusController::class, 'groups']);
        Route::get('/tooth-statuses/history/recent', [ToothStatusController::class, 'recentHistory']);
        Route::get('/tooth-statuses/{tooth}', [ToothStatusController::class, 'show'])->whereNumber('tooth');
        Route::get('/tooth-statuses/{tooth}/history', [ToothStatusController::class, 'history'])->whereNumber('tooth');
    });

    // Doctors propose new/updated tooth statuses (A1) — admin still approves.
    Route::middleware('role:bac_si')->group(function () {
        Route::post('/tooth-status-proposals', [ToothStatusController::class, 'storeProposal']);
    });

    // Admin-only mutations on the master data + proposal review.
    Route::middleware('role:admin')->group(function () {
        Route::post('/tooth-statuses', [ToothStatusController::class, 'store']);
        Route::put('/tooth-statuses/{tooth}', [ToothStatusController::class, 'update'])->whereNumber('tooth');
        Route::post('/tooth-statuses/{tooth}/toggle-active', [ToothStatusController::class, 'toggleActive'])->whereNumber('tooth');
        Route::delete('/tooth-statuses/{tooth}', [ToothStatusController::class, 'destroy'])->whereNumber('tooth');
        Route::post('/tooth-statuses/reorder', [ToothStatusController::class, 'reorder']);

        Route::post('/tooth-status-groups', [ToothStatusController::class, 'storeGroup']);
        Route::put('/tooth-status-groups/{group}', [ToothStatusController::class, 'updateGroup'])->whereNumber('group');

        Route::get('/tooth-status-proposals', [ToothStatusController::class, 'listProposals']);
        Route::post('/tooth-status-proposals/{proposal}/approve', [ToothStatusController::class, 'approveProposal'])->whereNumber('proposal');
        Route::post('/tooth-status-proposals/{proposal}/reject', [ToothStatusController::class, 'rejectProposal'])->whereNumber('proposal');
    });
});
