<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServicePriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ServiceSeeder::class);
    }

    public function test_initial_price_is_created_when_service_is_created(): void
    {
        Sanctum::actingAs($this->createUser('admin'));

        $service = Service::first();
        // Seeder creates services -> ServiceCatalogService::createInitialPrice runs
        // For seeded services, check there is at least one price record
        $this->assertGreaterThan(0, ServicePrice::where('service_id', $service->id)->count());
    }

    public function test_admin_can_list_prices_grouped_by_service(): void
    {
        Sanctum::actingAs($this->createUser('admin'));

        $response = $this->getJson('/api/service-prices');
        $response->assertOk()->assertJsonStructure(['data', 'current_page', 'last_page']);
    }

    public function test_admin_can_view_timeline_for_service(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $response = $this->getJson("/api/service-prices/services/{$service->id}/timeline");
        $response->assertOk()->assertJsonStructure([
            'service' => ['id', 'name', 'service_code'],
            'current',
            'future',
            'past',
            'pending',
        ]);
    }

    public function test_admin_create_price_apply_now_ends_current_record(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();
        $prevActive = ServicePrice::where('service_id', $service->id)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->first();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 999000,
            'apply_now' => true,
            'reason' => 'Tang gia test',
        ]);

        $response->assertCreated();

        if ($prevActive) {
            $prev = ServicePrice::find($prevActive->id);
            $this->assertSame(ServicePrice::STATUS_EXPIRED, $prev->status, 'Previous active record should be expired.');
            $this->assertNotNull($prev->effective_to);
        }

        $newActive = ServicePrice::where('service_id', $service->id)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->first();
        $this->assertNotNull($newActive);
        $this->assertEqualsWithDelta(999000, (float) $newActive->price, 0.01);
    }

    public function test_admin_create_future_price_is_scheduled(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 555000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
            'reason' => 'Future price',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', ServicePrice::STATUS_SCHEDULED);
    }

    public function test_e1_zero_or_negative_price_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 0,
            'apply_now' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_e2_invalid_date_range_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 100000,
            'effective_from' => Carbon::now()->addMonths(3)->toDateString(),
            'effective_to' => Carbon::now()->addMonth()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_e3_overlapping_dates_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 555000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
            'effective_to' => Carbon::now()->addMonths(2)->toDateString(),
        ])->assertCreated();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 666000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->addDays(10)->toDateString(),
            'effective_to' => Carbon::now()->addMonths(3)->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('E3', $response->json('message') ?? '');
    }

    public function test_e5_cannot_edit_active_record(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();
        $active = ServicePrice::where('service_id', $service->id)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->first();
        $this->assertNotNull($active);

        $response = $this->putJson("/api/service-prices/{$active->id}", [
            'price' => 1234567,
            'effective_from' => $active->effective_from->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_can_edit_scheduled_future_record(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $created = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 222000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
        ])->assertCreated()->json();

        $response = $this->putJson("/api/service-prices/{$created['id']}", [
            'price' => 333000,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
        ]);

        $response->assertOk()->assertJsonPath('price', '333000.00');
    }

    public function test_e6_cannot_delete_active_record(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();
        $active = ServicePrice::where('service_id', $service->id)
            ->where('status', ServicePrice::STATUS_ACTIVE)
            ->first();

        $response = $this->deleteJson("/api/service-prices/{$active->id}");
        $response->assertStatus(422);
    }

    public function test_can_delete_scheduled_record(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $created = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 222000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
        ])->assertCreated()->json();

        $response = $this->deleteJson("/api/service-prices/{$created['id']}");
        $response->assertOk();
        $this->assertDatabaseMissing('service_prices', ['id' => $created['id']]);
    }

    public function test_a1_accountant_proposes_admin_approves(): void
    {
        $accountant = $this->createUser('ke_toan');
        Sanctum::actingAs($accountant);

        $service = Service::first();
        $proposal = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 750000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
            'reason' => 'De xuat tu ke toan',
        ])->assertCreated()->json();

        $this->assertSame('pending', $proposal['proposal_status']);

        Sanctum::actingAs($this->createUser('admin'));
        $approved = $this->postJson("/api/service-prices/{$proposal['id']}/approve")
            ->assertOk()
            ->json();

        $this->assertSame('approved', $approved['proposal_status']);
    }

    public function test_a1_admin_can_reject_proposal(): void
    {
        $accountant = $this->createUser('ke_toan');
        Sanctum::actingAs($accountant);

        $service = Service::first();
        $proposal = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 750000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
        ])->assertCreated()->json();

        Sanctum::actingAs($this->createUser('admin'));
        $rejected = $this->postJson("/api/service-prices/{$proposal['id']}/reject", [
            'reason' => 'Khong dong y',
        ])->assertOk()->json();

        $this->assertSame('rejected', $rejected['proposal_status']);
    }

    public function test_non_admin_non_accountant_cannot_create(): void
    {
        Sanctum::actingAs($this->createUser('le_tan'));
        $service = Service::first();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 1000,
            'apply_now' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_only_admin_can_approve(): void
    {
        $accountant = $this->createUser('ke_toan');
        Sanctum::actingAs($accountant);
        $service = Service::first();

        $proposal = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'price' => 100000,
            'apply_now' => false,
            'effective_from' => Carbon::now()->addMonth()->toDateString(),
        ])->assertCreated()->json();

        // Accountant cannot approve own proposal
        $response = $this->postJson("/api/service-prices/{$proposal['id']}/approve");
        $response->assertForbidden();
    }

    public function test_e8_missing_data_rejected(): void
    {
        Sanctum::actingAs($this->createUser('admin'));
        $service = Service::first();

        $response = $this->postJson('/api/service-prices', [
            'service_id' => $service->id,
            'apply_now' => false,
            // missing price + effective_from
        ]);

        $response->assertStatus(422);
    }

    private function createUser(string $roleSlug): User
    {
        $user = User::factory()->create([
            'name' => $roleSlug.' user',
            'email' => $roleSlug.'-prc@example.com',
            'username' => $roleSlug.'_prc',
            'password' => Hash::make('Password@123'),
        ]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);

        return $user;
    }
}
