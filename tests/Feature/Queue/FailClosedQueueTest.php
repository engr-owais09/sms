<?php

namespace Tests\Feature\Queue;

use App\Core\Tenancy\Contracts\PlatformSystemJob;
use App\Core\Tenancy\Contracts\TenantAwareJob;
use App\Core\Tenancy\Exceptions\MissingTenantContextException;
use App\Core\Tenancy\Exceptions\TenantSuspendedException;
use App\Core\Tenancy\Middleware\EnforceTenantQueueMiddleware;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestTenantJob implements TenantAwareJob
{
    public function __construct(public ?string $tenantId)
    {
    }

    public function getTenantId(): string
    {
        return $this->tenantId ?? '';
    }
}

class TestSystemJob implements PlatformSystemJob
{
    public bool $executed = false;
}

class TestUnmarkedJob
{
    // Lacks both interfaces
}

class FailClosedQueueTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $activeTenant;
    protected Tenant $suspendedTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeTenant = Tenant::create([
            'name' => 'Active High School',
            'slug' => 'active-school',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->suspendedTenant = Tenant::create([
            'name' => 'Suspended Institute',
            'slug' => 'suspended-institute',
            'type' => 'academy',
            'status' => 'suspended',
        ]);
    }

    public function test_unmarked_job_fails_closed_with_missing_tenant_exception(): void
    {
        $middleware = new EnforceTenantQueueMiddleware();
        $job = new TestUnmarkedJob();

        $this->expectException(MissingTenantContextException::class);

        $middleware->handle($job, fn() => true);
    }

    public function test_tenant_job_with_empty_tenant_id_fails_closed(): void
    {
        $middleware = new EnforceTenantQueueMiddleware();
        $job = new TestTenantJob(null);

        $this->expectException(MissingTenantContextException::class);

        $middleware->handle($job, fn() => true);
    }

    public function test_tenant_job_with_nonexistent_tenant_fails_closed(): void
    {
        $middleware = new EnforceTenantQueueMiddleware();
        $job = new TestTenantJob('00000000-0000-0000-0000-000000000000');

        $this->expectException(MissingTenantContextException::class);

        $middleware->handle($job, fn() => true);
    }

    public function test_tenant_job_for_suspended_tenant_is_halted(): void
    {
        $middleware = new EnforceTenantQueueMiddleware();
        $job = new TestTenantJob($this->suspendedTenant->id);

        $this->expectException(TenantSuspendedException::class);

        $middleware->handle($job, fn() => true);
    }

    public function test_valid_tenant_job_executes_with_established_context_and_cleans_up(): void
    {
        $middleware = new EnforceTenantQueueMiddleware();
        $job = new TestTenantJob($this->activeTenant->id);

        $executed = false;
        $contextInsideJob = null;
        $dbSettingInsideJob = null;

        $middleware->handle($job, function () use (&$executed, &$contextInsideJob, &$dbSettingInsideJob) {
            $executed = true;
            $contextInsideJob = app(TenantContext::class)->getTenantId();
            $dbSettingInsideJob = PostgresTenantContext::get();
        });

        $this->assertTrue($executed);
        $this->assertEquals($this->activeTenant->id, $contextInsideJob);
        $this->assertEquals($this->activeTenant->id, $dbSettingInsideJob);

        // After job completion, context must be wiped
        $this->assertNull(app(TenantContext::class)->getTenant());
    }

    public function test_platform_system_job_executes_without_tenant_context(): void
    {
        $middleware = new EnforceTenantQueueMiddleware();
        $job = new TestSystemJob();

        $executed = false;

        $middleware->handle($job, function ($j) use (&$executed) {
            $executed = true;
            $j->executed = true;
        });

        $this->assertTrue($executed);
        $this->assertTrue($job->executed);
        $this->assertNull(app(TenantContext::class)->getTenant());
    }
}
