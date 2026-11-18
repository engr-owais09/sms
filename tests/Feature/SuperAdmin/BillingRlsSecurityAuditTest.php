<?php

namespace Tests\Feature\SuperAdmin;

use App\Core\Tenancy\PostgresTenantContext;
use App\Modules\Institute\Models\Tenant;
use App\Modules\SuperAdmin\Models\SubscriptionPlan;
use App\Modules\SuperAdmin\Models\TenantSubscription;
use App\Modules\SuperAdmin\Services\SubscriptionPlanService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingRlsSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected array $tenantBillingTables = [
        'tenant_subscriptions',
        'subscription_invoices',
        'subscription_payments',
    ];

    public function test_tenant_billing_tables_have_rls_enabled_forced_and_isolated(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS security audit requires PostgreSQL.');
        }

        $records = DB::select("
            SELECT c.relname as table_name, c.relrowsecurity as rls_enabled, c.relforcerowsecurity as rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public'
              AND c.relkind = 'r'
              AND c.relname IN ('" . implode("', '", $this->tenantBillingTables) . "')
        ");

        $catalog = [];
        foreach ($records as $r) {
            $catalog[$r->table_name] = [
                'enabled' => (bool) $r->rls_enabled,
                'forced' => (bool) $r->rls_forced,
            ];
        }

        foreach ($this->tenantBillingTables as $table) {
            $this->assertArrayHasKey($table, $catalog);
            $this->assertTrue($catalog[$table]['enabled']);
            $this->assertTrue($catalog[$table]['forced']);
        }

        (new SubscriptionPlanService())->seedDefaultPlans();
        $plan = SubscriptionPlan::where('code', 'starter')->firstOrFail();

        $tenantA = Tenant::create(['name' => 'Bill A', 'slug' => 'bill-rls-a', 'type' => 'school', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Bill B', 'slug' => 'bill-rls-b', 'type' => 'school', 'status' => 'active']);

        $subA = TenantSubscription::create([
            'tenant_id' => $tenantA->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
        ]);

        $subB = TenantSubscription::create([
            'tenant_id' => $tenantB->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonth()->toDateString(),
        ]);

        DB::statement('SET ROLE educore_app');
        DB::statement('RESET app.current_tenant_id');

        try {
            $this->assertEmpty(DB::table('tenant_subscriptions')->get());

            PostgresTenantContext::set($tenantA->id);

            $visible = DB::table('tenant_subscriptions')->pluck('id')->toArray();
            $this->assertContains($subA->id, $visible);
            $this->assertNotContains($subB->id, $visible);

            $blocked = false;
            DB::statement('SAVEPOINT bill_rls_inject');
            try {
                DB::table('tenant_subscriptions')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantB->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => 'active',
                    'current_period_start' => now()->toDateString(),
                    'current_period_end' => now()->addMonth()->toDateString(),
                    'auto_renew' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT bill_rls_inject');
                $blocked = true;
                $this->assertStringContainsString('violates row-level security policy', $e->getMessage());
            }

            $this->assertTrue($blocked);
        } finally {
            try {
                DB::statement('RESET ROLE');
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    try { DB::rollBack(); } catch (\Throwable $t) {}
                }
                DB::statement('RESET ROLE');
            }
        }
    }
}
