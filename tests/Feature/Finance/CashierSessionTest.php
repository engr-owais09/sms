<?php

namespace Tests\Feature\Finance;

use App\Core\Tenancy\PostgresTenantContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Services\CashierSessionService;
use App\Modules\Finance\Services\GeneralLedgerService;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CashierSessionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected User $cashier;
    protected User $supervisor;
    protected CashierSessionService $sessionService;
    protected GeneralLedgerService $glService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Lahore Grammar Main Campus',
            'slug' => 'lgs-main',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gulberg Branch',
            'code' => 'GB-01',
            'city' => 'Lahore',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);

        $this->cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cashier Aslam',
            'email' => 'aslam@educore.test',
        ]);

        $this->supervisor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Supervisor Bashir',
            'email' => 'bashir@educore.test',
        ]);

        $this->glService = new GeneralLedgerService();
        $this->glService->seedDefaultChartOfAccounts($this->tenant->id);

        $this->sessionService = new CashierSessionService();
    }

    public function test_can_open_cashier_session_and_blocks_concurrent_open_sessions(): void
    {
        $session = $this->sessionService->openSession($this->cashier, $this->branch, 5000.00);

        $this->assertNotNull($session->id);
        $this->assertEquals('open', $session->status);
        $this->assertEquals(5000.00, $session->opening_float);
        $this->assertEquals(5000.00, $session->expected_cash_balance);

        // Attempting to open another session for the same cashier is blocked
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already has an open session');

        $this->sessionService->openSession($this->cashier, $this->branch, 2000.00);
    }

    public function test_blind_close_computes_denominations_and_variance(): void
    {
        $session = $this->sessionService->openSession($this->cashier, $this->branch, 5000.00);

        // Record cash receipts: Rs. 20,000
        $this->sessionService->recordDrawerReceipt($session, 20000.00);

        // Record petty cash payout: Rs. 1,000
        $this->sessionService->recordDrawerPayout($session, 1000.00, 'Urgent postage stamps');

        // Expected in drawer = 5,000 + 20,000 - 1,000 = 24,000.00
        $session->refresh();
        $this->assertEquals(24000.00, $session->expected_cash_balance);

        // Cashier blind count:
        // 5000 x 4 = 20,000
        // 1000 x 3 = 3,000
        // 500 x 2 = 1,000
        // Total physical cash counted = 24,000.00 (Zero variance)
        $denominations = [
            '5000' => 4,
            '1000' => 3,
            '500' => 2,
        ];

        $closedSession = $this->sessionService->closeSessionBlind($session, $denominations, 'All notes verified');

        $this->assertEquals('closed', $closedSession->status);
        $this->assertNotNull($closedSession->closed_at);
        $this->assertEquals(24000.00, $closedSession->actual_counted_cash);
        $this->assertEquals(0.00, $closedSession->variance_amount);
        $this->assertEquals($denominations, $closedSession->denominations);
    }

    public function test_supervisor_verification_posts_bank_deposit_and_shortage_variance_gl_entry(): void
    {
        $session = $this->sessionService->openSession($this->cashier, $this->branch, 2000.00);

        // Expected in drawer = 2,000
        // Cashier counts 1,800 (Shortage of Rs. 200)
        $closedSession = $this->sessionService->closeSessionBlind($session, 1800.00, 'Minor change missing');

        $this->assertEquals(-200.00, $closedSession->variance_amount);

        // Supervisor verifies and posts
        $closingGl = $this->sessionService->verifyAndPostClosing($closedSession, $this->supervisor, '1021');

        $this->assertNotNull($closingGl);
        $this->assertEquals('posted', $closingGl->status);
        $this->assertEquals('cashier_closing', $closingGl->source_type);

        $closedSession->refresh();
        $this->assertEquals('reconciled', $closedSession->status);
        $this->assertEquals($this->supervisor->id, $closedSession->reconciled_by_user_id);
        $this->assertEquals($closingGl->id, $closedSession->closing_journal_entry_id);

        // Verify GL debits and credits:
        // Debits:
        // - 1021 Bank Collection: 1,800
        // - 5030 Cash Shortage Expense: 200
        // Credits:
        // - 1010 Cash on Hand: 1,800 (Bank deposit) + 200 (Shortage) = 2,000
        $lines = $closingGl->lines;
        $this->assertEquals(2000.00, $lines->where('entry_type', 'debit')->sum('amount'));
        $this->assertEquals(2000.00, $lines->where('entry_type', 'credit')->sum('amount'));

        $shortageLine = $lines->first(fn($l) => $l->entry_type === 'debit' && $l->account->account_code === '5030');
        $this->assertNotNull($shortageLine);
        $this->assertEquals(200.00, (float) $shortageLine->amount);
    }

    public function test_immediate_rls_isolation_on_cashier_sessions(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS test requires PostgreSQL.');
        }

        $tenantB = Tenant::create([
            'name' => 'Beaconhouse Defence Campus',
            'slug' => 'bhs-defence',
            'type' => 'school',
            'status' => 'active',
        ]);

        $branchB = Branch::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Defence Branch',
            'code' => 'BHS-01',
            'city' => 'Lahore',
        ]);

        $cashierB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Cashier B',
            'email' => 'cashierb@educore.test',
        ]);

        $sessionA = $this->sessionService->openSession($this->cashier, $this->branch, 1000.00);

        $sessionB = CashierSession::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'cashier_user_id' => $cashierB->id,
            'session_code' => 'CS-B-001',
            'status' => 'open',
            'opened_at' => now(),
            'opening_float' => 2000.00,
            'expected_cash_balance' => 2000.00,
        ]);

        // Switch to unprivileged role educore_app under Tenant A context
        DB::statement('SET ROLE educore_app');
        PostgresTenantContext::set($this->tenant->id);

        try {
            $visibleSessions = DB::table('cashier_sessions')->pluck('id')->toArray();

            $this->assertContains($sessionA->id, $visibleSessions);
            $this->assertNotContains($sessionB->id, $visibleSessions);
        } finally {
            DB::statement('RESET ROLE');
        }
    }
}

