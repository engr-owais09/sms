<?php

namespace Tests\Feature\Security;

use App\Modules\Institute\Services\TenantProvisioningService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PhaseR7AndR8UxOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_and_attaches_request_id_header(): void
    {
        $resp = $this->get('http://127.0.0.1/health');
        $resp->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                    'storage' => true,
                ],
            ]);

        $this->assertNotEmpty($resp->headers->get('X-Request-Id'));
    }

    public function test_theming_dark_mode_accessibility_skip_link_and_urdu_validation(): void
    {
        (new RoleAndPermissionSeeder())->run();

        $prov = app(TenantProvisioningService::class)->provision([
            'name' => 'Theme School',
            'slug' => 'theme-school',
            'type' => 'school',
            'owner_name' => 'Owner',
            'owner_email' => 'owner@theme.test',
            'owner_password' => 'StrongPass123!',
        ]);
        $prov['tenant']->update(['settings' => ['primary_color' => '#0f766e']]);

        $dark = $this->get('http://theme-school.educore.test/?theme=dark');
        $dark->assertOk();
        $dark->assertSee('data-theme="dark"', false);
        $dark->assertSee('--brand-primary: #0f766e;', false);
        $dark->assertSee('href="#main-content"', false);

        App::setLocale('ur');
        $v = Validator::make(['email' => ''], ['email' => 'required']);
        $this->assertStringContainsString('ضروری ہے', $v->errors()->first('email'));
    }
}
