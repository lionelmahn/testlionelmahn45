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
            'packages' => 'Goi dich vu',
            'appointments' => 'Lich hen',
            'dental_records' => 'Kham nha khoa',
            'finance' => 'Tai chinh',
            'reports' => 'Bao cao',
            'schedules' => 'Lich lam viec',
            'prices' => 'Bang gia dich vu',
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

        // Grant services.view + packages.view to all non-admin roles so they
        // can browse the catalog (visibility/status scope enforced server-side).
        $sharedView = Permission::whereIn('slug', ['services.view', 'packages.view'])->pluck('id')->all();
        if (! empty($sharedView)) {
            foreach (['bac_si', 'le_tan', 'ke_toan', 'benh_nhan'] as $slug) {
                $role = Role::where('slug', $slug)->first();
                if ($role) {
                    $role->permissions()->syncWithoutDetaching($sharedView);
                }
            }
        }

        // Grant prices.view to internal staff so they can read pricing.
        $pricesView = Permission::where('slug', 'prices.view')->value('id');
        if ($pricesView) {
            foreach (['bac_si', 'le_tan', 'ke_toan'] as $slug) {
                $role = Role::where('slug', $slug)->first();
                if ($role) {
                    $role->permissions()->syncWithoutDetaching([$pricesView]);
                }
            }
        }

        // Accountant can propose new prices (Admin still approves).
        $accountantExtra = Permission::whereIn('slug', ['prices.create', 'prices.edit'])->pluck('id')->all();
        if (! empty($accountantExtra)) {
            $accountant = Role::where('slug', 'ke_toan')->first();
            if ($accountant) {
                $accountant->permissions()->syncWithoutDetaching($accountantExtra);
            }
        }
    }
}
