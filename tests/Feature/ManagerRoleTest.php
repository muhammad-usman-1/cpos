<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $slug, array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::query()->where('slug', $slug)->value('id')]);
    }

    public function test_manager_can_log_in(): void
    {
        $manager = $this->userWithRole(Role::MANAGER, ['username' => 'ali']);

        $this->post(route('login'), ['login' => 'ali', 'password' => 'password'])->assertRedirect(route('sales.create'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_tailor_cannot_log_in(): void
    {
        $this->userWithRole('tailor', ['username' => 'tailor-a']);

        $this->post(route('login'), ['login' => 'tailor-a', 'password' => 'password'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_deactivated_manager_cannot_log_in(): void
    {
        $this->userWithRole(Role::MANAGER, ['username' => 'ali', 'is_active' => false]);

        $this->post(route('login'), ['login' => 'ali', 'password' => 'password'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_manager_reaches_shop_floor_pages_only(): void
    {
        $manager = $this->userWithRole(Role::MANAGER);

        foreach (['dashboard', 'sales.create', 'sales.index', 'orders.index', 'orders.due', 'tailors.index', 'customers.index'] as $routeName) {
            $this->actingAs($manager)->get(route($routeName))->assertOk();
        }

        foreach (['purchases.index', 'suppliers.index', 'profit.index', 'settings.edit', 'staff.index'] as $routeName) {
            $this->actingAs($manager)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_owner_adds_a_manager_who_can_then_log_in(): void
    {
        $owner = $this->userWithRole(Role::OWNER);

        $this->actingAs($owner)->get(route('staff.index'))->assertOk();

        $this->actingAs($owner)->post(route('staff.store'), [
            'name' => 'Bilal',
            'username' => 'bilal',
            'phone' => '03001234567',
            'password' => 'secret12',
        ])->assertSessionHasNoErrors();

        $manager = User::query()->where('username', 'bilal')->firstOrFail();
        $this->assertTrue($manager->isManager());

        auth()->logout();
        $this->post(route('login'), ['login' => 'bilal', 'password' => 'secret12']);
        $this->assertAuthenticatedAs($manager);
    }

    public function test_owner_deactivates_a_manager_and_keeps_the_password_when_left_blank(): void
    {
        $owner = $this->userWithRole(Role::OWNER);
        $manager = $this->userWithRole(Role::MANAGER, ['username' => 'ali']);
        $passwordHash = $manager->password;

        $this->actingAs($owner)->put(route('staff.update', $manager), [
            'name' => 'Ali Raza',
            'username' => 'ali',
            'password' => '',
        ])->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame('Ali Raza', $manager->name);
        $this->assertFalse($manager->is_active);
        $this->assertSame($passwordHash, $manager->password);
    }

    public function test_staff_page_does_not_edit_the_owner(): void
    {
        $owner = $this->userWithRole(Role::OWNER);

        $this->actingAs($owner)->put(route('staff.update', $owner), ['name' => 'X', 'username' => 'x'])->assertNotFound();
    }
}
