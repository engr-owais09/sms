<?php

namespace Tests\Feature\Pilot;

use App\Modules\Institute\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotSeederCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_seeder_command_is_idempotent_in_testing_environment(): void
    {
        $this->artisan('educore:seed-pilot')
            ->assertExitCode(0);

        $this->artisan('educore:seed-pilot')
            ->assertExitCode(0);

        $this->assertEquals(1, Tenant::where('slug', 'pilot-lgs')->count());
        $this->assertDatabaseHas('domains', ['domain' => 'pilot.localhost']);
    }

    public function test_pilot_seeder_command_refuses_to_run_in_production(): void
    {
        $originalEnv = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->artisan('educore:seed-pilot')
                ->expectsOutputToContain('SAFETY GUARD')
                ->assertExitCode(1);
        } finally {
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }
}
