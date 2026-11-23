<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseR0FoundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_seeder_does_not_create_hardcoded_super_admin_without_env(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('roles', ['name' => 'super_admin', 'tenant_id' => null]);
    }

    public function test_create_super_admin_command_enforces_password_strength_and_assigns_role(): void
    {
        $this->artisan('educore:create-super-admin', [
            '--name' => 'Weak Admin',
            '--email' => 'weak@educore.test',
            '--password' => 'short123',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'weak@educore.test']);

        $this->artisan('educore:create-super-admin', [
            '--name' => 'Platform Admin',
            '--email' => 'platform.admin@educore.test',
            '--password' => 'UltraStrongPass2026!',
        ])->assertExitCode(0);

        $user = User::where('email', 'platform.admin@educore.test')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue(Hash::check('UltraStrongPass2026!', $user->password));
        setPermissionsTeamId(null);
        $this->assertTrue($user->hasRole('super_admin'));
    }
}
