<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_admin_seeder_creates_the_configured_login(): void
    {
        config([
            'default_admin.name' => 'School Admin',
            'default_admin.email' => 'school-admin@example.test',
            'default_admin.password' => 'a-strong-test-password',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'school-admin@example.test')->firstOrFail();
        $this->assertSame('School Admin', $admin->name);
        $this->assertTrue(Hash::check('a-strong-test-password', $admin->password));
    }

    public function test_reseeding_does_not_reset_an_existing_admin_password(): void
    {
        config([
            'default_admin.name' => 'School Admin',
            'default_admin.email' => 'school-admin@example.test',
            'default_admin.password' => 'new-seed-password',
        ]);
        $admin = User::factory()->create([
            'name' => 'Existing Admin',
            'email' => 'school-admin@example.test',
            'password' => 'existing-password',
        ]);
        $existingHash = $admin->password;

        $this->seed(AdminUserSeeder::class);

        $this->assertSame($existingHash, $admin->fresh()->password);
        $this->assertSame('Existing Admin', $admin->fresh()->name);
    }

    public function test_local_admin_seed_route_is_unavailable_outside_local_environment(): void
    {
        $this->assertFalse(Route::has('local.admin-seed'));
        $this->assertFalse(Route::has('local.admin-seed.store'));
        $this->get('/seed-admin')->assertNotFound();
        $this->post('/seed-admin')->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_flash_messages_can_render_without_a_shared_validation_error_bag(): void
    {
        $this->view('components.flash-messages')->assertOk();
    }
}
