<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'users' => 'Quan ly nguoi dung',
            'staff' => 'Nhan su',
            'professional_profiles' => 'Ho so chuyen mon',
            'categories' => 'Danh muc',
            'patients' => 'Benh nhan',
            'services' => 'Dich vu nha khoa',
            'appointments' => 'Lich hen',
            'dental_records' => 'Kham nha khoa',
            'finance' => 'Tai chinh',
            'reports' => 'Bao cao',
        ];

        $actions = [
            'view' => 'Xem',
            'create' => 'Them',
            'edit' => 'Sua',
            'delete' => 'Xoa',
            'approve' => 'Duyet/Xac nhan',
            'export' => 'In/Xuat file',
        ];

        $permissionIds = [];

        foreach ($modules as $moduleSlug => $moduleName) {
            foreach ($actions as $actionSlug => $actionName) {
                $permission = Permission::firstOrCreate(
                    ['slug' => "{$moduleSlug}.{$actionSlug}"],
                    [
                        'name' => "{$actionName} {$moduleName}",
                        'module' => $moduleSlug,
                    ]
                );
                $permissionIds[] = $permission->id;
            }
        }

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync($permissionIds);
        }

        // Grant services.view to all non-admin roles so they can browse the
        // catalog (visibility/status scope is enforced server-side per role).
        $servicesView = Permission::where('slug', 'services.view')->first();
        if ($servicesView) {
            foreach (['bac_si', 'le_tan', 'ke_toan', 'benh_nhan'] as $slug) {
                $role = Role::where('slug', $slug)->first();
                if ($role) {
                    $role->permissions()->syncWithoutDetaching([$servicesView->id]);
                }
            }
        }
    }
}
