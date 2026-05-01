<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ServicePackageSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicePackageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ServiceSeeder::class);
        $this->seed(ServicePackageSeeder::class);
    }

    public function test_admin_can_create_package_in_draft(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::where('status', 'active')->first();

        $response = $this->postJson('/api/service-packages', [
            'code' => 'PKG999',
            'name' => 'Goi test moi',
            'description' => 'Mo ta',
            'visibility' => 'internal',
            'package_price' => 100000,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'usage_validity_days' => 30,
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('code', 'PKG999')
            ->assertJsonPath('status', 'draft');

        $this->assertDatabaseHas('service_packages', ['code' => 'PKG999']);
        $this->assertDatabaseHas('service_package_items', [
            'service_id' => $service->id,
        ]);
        $this->assertDatabaseHas('service_package_history', [
            'action' => 'created',
        ]);
    }

    public function test_e1_duplicate_code_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $response = $this->postJson('/api/service-packages', [
            'code' => 'PKG001',
            'name' => 'Trung ma',
            'package_price' => 0,
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_e2_no_items_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));

        $response = $this->postJson('/api/service-packages', [
            'code' => 'PKG_E2',
            'name' => 'Khong dich vu',
            'package_price' => 0,
            'items' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_e3_package_price_above_sum_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::where('status', 'active')->first();

        $response = $this->postJson('/api/service-packages', [
            'code' => 'PKG_E3',
            'name' => 'Gia goi qua cao',
            'package_price' => $service->price * 2 + 1000,
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1, 'unit_price' => $service->price],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_e8_invalid_date_range_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::where('status', 'active')->first();

        $response = $this->postJson('/api/service-packages', [
            'code' => 'PKG_E8',
            'name' => 'Sai khoang ngay',
            'package_price' => 0,
            'effective_from' => '2026-12-01',
            'effective_to' => '2026-01-01',
            'items' => [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_e6_cannot_activate_with_discontinued_service(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $svc = Service::first();
        $svc->update(['status' => Service::STATUS_DISCONTINUED]);

        $response = $this->postJson('/api/service-packages', [
            'code' => 'PKG_E6',
            'name' => 'Goi voi dich vu ngung',
            'status' => 'active',
            'package_price' => 100000,
            'items' => [
                ['service_id' => $svc->id, 'quantity' => 1, 'unit_price' => $svc->price],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_change_status_and_history_recorded(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $package = ServicePackage::where('status', 'active')->first();

        $response = $this->postJson('/api/service-packages/'.$package->id.'/status', [
            'status' => 'hidden',
            'reason' => 'Tam an',
        ]);

        $response->assertOk()->assertJsonPath('status', 'hidden');
        $this->assertDatabaseHas('service_package_history', [
            'package_id' => $package->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_admin_can_clone_package(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $package = ServicePackage::first();

        $response = $this->postJson('/api/service-packages/'.$package->id.'/clone', [
            'name' => 'Goi nhan ban moi',
        ]);

        $response->assertCreated()
            ->assertJsonPath('parent_package_id', $package->id)
            ->assertJsonPath('status', 'draft');
    }

    public function test_admin_can_delete_package_without_transactions(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $package = ServicePackage::where('status', 'draft')->first();

        $response = $this->deleteJson('/api/service-packages/'.$package->id);

        $response->assertOk();
        $this->assertDatabaseMissing('service_packages', ['id' => $package->id]);
    }

    public function test_patient_only_sees_public_active_packages(): void
    {
        Sanctum::actingAs($this->createUser('benh_nhan'));

        $response = $this->getJson('/api/service-packages');

        $response->assertOk();
        foreach ($response->json('data') as $pkg) {
            $this->assertSame('active', $pkg['status']);
            $this->assertSame('public', $pkg['visibility']);
        }
    }

    private function createUser(string $roleSlug): User
    {
        $user = User::factory()->create([
            'name' => $roleSlug.' user',
            'email' => $roleSlug.'-pkg@example.com',
            'username' => $roleSlug.'_pkg',
            'password' => Hash::make('Password@123'),
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);

        return $user;
    }
}
