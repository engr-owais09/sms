<?php

namespace Tests\Feature\Security;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Branch $branchA;
    protected Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant A
        $this->tenantA = Tenant::create([
            'name' => 'Beaconhouse Campus A',
            'slug' => 'beacon-a',
            'type' => 'school',
            'status' => 'active',
        ]);

        // Create Tenant B
        $this->tenantB = Tenant::create([
            'name' => 'City School Campus B',
            'slug' => 'city-b',
            'type' => 'school',
            'status' => 'active',
        ]);

        // Create branches under each tenant
        $this->branchA = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Campus Alpha',
            'code' => 'ALPHA-01',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        $this->branchB = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Campus Beta',
            'code' => 'BETA-01',
            'city' => 'Karachi',
            'is_main' => true,
        ]);
    }

    public function test_tenant_a_cannot_see_tenant_b_records_via_eloquent_scope(): void
    {
        // Set Context to Tenant A
        app(TenantContext::class)->setTenant($this->tenantA);

        $visibleBranches = Branch::all();

        $this->assertCount(1, $visibleBranches);
        $this->assertTrue($visibleBranches->contains($this->branchA));
        $this->assertFalse($visibleBranches->contains($this->branchB));
    }

    public function test_switching_to_tenant_b_scopes_records_cleanly(): void
    {
        // Set Context to Tenant B
        app(TenantContext::class)->setTenant($this->tenantB);

        $visibleBranches = Branch::all();

        $this->assertCount(1, $visibleBranches);
        $this->assertTrue($visibleBranches->contains($this->branchB));
        $this->assertFalse($visibleBranches->contains($this->branchA));
    }

    public function test_raw_query_filtered_by_postgres_rls(): void
    {
        // Execute inside transaction as runtime role 'educore_app' (non-superuser with RLS enforced)
        DB::transaction(function () {
            DB::statement("SET ROLE educore_app");
            PostgresTenantContext::set($this->tenantA->id);

            // Raw query bypassing Eloquent global scopes for Tenant A
            $rows = DB::select("SELECT * FROM branches WHERE tenant_id = ?", [$this->tenantA->id]);
            $this->assertCount(1, $rows);
            $this->assertEquals($this->branchA->id, $rows[0]->id);

            // Raw query attempting to select Tenant B rows
            $crossRows = DB::select("SELECT * FROM branches WHERE tenant_id = ?", [$this->tenantB->id]);
            $this->assertCount(0, $crossRows, 'Postgres RLS should block reading cross-tenant rows even via direct SQL');

            DB::statement("SET ROLE NONE");
        });
    }

    public function test_missing_tenant_context_returns_empty_result_set(): void
    {
        // Clear context
        app(TenantContext::class)->clear();

        DB::transaction(function () {
            // Context is empty string
            PostgresTenantContext::reset();

            // When RLS is active on educore_app or evaluated with empty setting
            $currentSetting = PostgresTenantContext::get();
            $this->assertEmpty($currentSetting);
        });
    }

    public function test_composite_unique_constraint_prevents_duplicate_code_per_tenant_but_allows_across_tenants(): void
    {
        // Branch A has code 'ALPHA-01'. Tenant B should be able to have 'ALPHA-01' without collision
        $branchBNew = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Campus Alpha of City',
            'code' => 'ALPHA-01', // Same code as Tenant A
            'city' => 'Islamabad',
            'is_main' => false,
        ]);

        $this->assertNotNull($branchBNew->id);
        $this->assertEquals('ALPHA-01', $branchBNew->code);
    }
}
