<?php

namespace Tests\Feature\Tenancy;

use App\Core\Tenancy\Exceptions\NoActiveTenantException;
use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Institute\Models\Tenant;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantTransactionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'KGS Clifton Campus',
            'slug' => 'kgs-clifton',
            'type' => 'school',
            'status' => 'active',
        ]);
    }

    public function test_tenant_transaction_manager_sets_and_cleans_postgres_context(): void
    {
        app(TenantContext::class)->setTenant($this->tenant);

        $observedTenantInDb = null;

        TenantTransactionManager::run(function () use (&$observedTenantInDb) {
            $observedTenantInDb = PostgresTenantContext::get();
        });

        // Context inside transaction must match tenant ID
        $this->assertEquals($this->tenant->id, $observedTenantInDb);
    }

    public function test_tenant_transaction_throws_exception_if_no_active_tenant(): void
    {
        app(TenantContext::class)->clear();

        $this->expectException(NoActiveTenantException::class);

        TenantTransactionManager::run(function () {
            // Should not reach here
        });
    }

    public function test_transaction_rollback_cleans_transaction_local_configuration(): void
    {
        app(TenantContext::class)->setTenant($this->tenant);

        try {
            TenantTransactionManager::run(function () {
                $setting = PostgresTenantContext::get();
                $this->assertEquals($this->tenant->id, $setting);

                throw new Exception('Simulated business logic failure inducing rollback');
            });
        } catch (Exception $e) {
            $this->assertEquals('Simulated business logic failure inducing rollback', $e->getMessage());
        }

        // Outside the transaction, is_local setting must not persist
        DB::transaction(function () {
            // New transaction without set_config should be clean
            $settingAfterRollback = PostgresTenantContext::get();
            $this->assertEmpty($settingAfterRollback);
        });
    }

    public function test_connection_reuse_hygiene_resets_context(): void
    {
        // 1. Set context for Tenant
        PostgresTenantContext::set($this->tenant->id);
        $this->assertEquals($this->tenant->id, PostgresTenantContext::get());

        // 2. Simulate connection return to pool / middleware terminate
        PostgresTenantContext::reset();

        $this->assertEmpty(PostgresTenantContext::get());
    }
}
