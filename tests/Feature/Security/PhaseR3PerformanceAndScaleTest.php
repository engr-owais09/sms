<?php

namespace Tests\Feature\Security;

use App\Core\Cache\TenantCache;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseR3PerformanceAndScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_prevent_lazy_loading_is_enabled_in_non_production(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
    }

    public function test_tenant_cache_isolates_keys_across_tenants(): void
    {
        $valA = TenantCache::remember('tenant-a', 'dashboard_stats', 60, fn () => ['count' => 10]);
        $valB = TenantCache::remember('tenant-b', 'dashboard_stats', 60, fn () => ['count' => 99]);

        $this->assertSame(10, $valA['count']);
        $this->assertSame(99, $valB['count']);

        TenantCache::forget('tenant-a', 'dashboard_stats');
        $this->assertSame(99, TenantCache::remember('tenant-b', 'dashboard_stats', 60, fn () => ['count' => 0])['count']);
    }

    public function test_synthetic_data_generator_creates_tenants_and_students(): void
    {
        $this->artisan('educore:seed-synthetic', ['--tenants' => 1, '--students' => 5])
            ->assertExitCode(0);

        $this->assertSame(1, Tenant::count());
        $this->assertSame(5, Student::withoutGlobalScopes()->count());
    }
}
