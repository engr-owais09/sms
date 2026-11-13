<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Student Credit Wallets (Account 2025)
        Schema::create('student_credit_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->string('currency', 10)->default('PKR');
            $table->string('status', 30)->default('active'); // 'active', 'suspended', 'closed'
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id']);
            $table->unique(['tenant_id', 'id']);
        });

        // 2. Student Credit Transactions
        Schema::create('student_credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_credit_wallet_id')->constrained('student_credit_wallets')->cascadeOnDelete();
            $table->string('transaction_type', 30); // 'credit', 'debit', 'refund'
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('source_type', 50); // 'overpayment', 'direct_deposit', 'invoice_payment', 'refund', 'reversal'
            $table->uuid('source_id')->nullable();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        // 3. Student Fee Advances (Accounts 2010 / 2015)
        Schema::create('student_fee_advances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->uuid('fee_payment_id')->nullable();
            $table->foreignUuid('fee_category_id')->nullable()->constrained('fee_categories')->nullOnDelete();
            $table->foreignUuid('gl_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->string('target_billing_period', 20); // 'YYYY-MM'
            $table->date('receipt_date');
            $table->string('receipt_number', 60);
            $table->decimal('amount', 12, 2);
            $table->decimal('applied_amount', 12, 2)->default(0.00);
            $table->decimal('refunded_amount', 12, 2)->default(0.00);
            $table->string('status', 30)->default('unapplied'); // 'unapplied', 'partially_applied', 'fully_applied', 'refunded', 'reversed'
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->unique(['tenant_id', 'id']);
        });

        // 4. Student Fee Advance Allocations (Allocation Ledger - Sole Authoritative Source of Truth)
        Schema::create('student_fee_advance_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_fee_advance_id')->constrained('student_fee_advances')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_item_id')->nullable()->constrained('fee_invoice_items')->nullOnDelete();
            $table->string('allocation_reference', 120);
            $table->foreignUuid('settlement_journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->date('allocation_date');
            $table->string('status', 20)->default('active'); // 'active', 'reversed'
            $table->foreignUuid('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'allocation_reference']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Computed column for remaining_balance on student_fee_advances
            DB::statement('
                ALTER TABLE student_fee_advances 
                ADD COLUMN remaining_balance NUMERIC(12, 2) 
                GENERATED ALWAYS AS (amount - applied_amount - refunded_amount) STORED
            ');

            DB::statement('ALTER TABLE student_credit_wallets ADD CONSTRAINT chk_wallet_balance_non_neg CHECK (balance >= 0)');
            DB::statement('ALTER TABLE student_credit_transactions ADD CONSTRAINT chk_wallet_tx_amount_pos CHECK (amount > 0)');
            DB::statement("ALTER TABLE student_credit_transactions ADD CONSTRAINT chk_wallet_tx_type CHECK (transaction_type IN ('credit', 'debit', 'refund'))");

            DB::statement('ALTER TABLE student_fee_advances ADD CONSTRAINT chk_advance_amount_pos CHECK (amount > 0)');
            DB::statement('ALTER TABLE student_fee_advances ADD CONSTRAINT chk_advance_applied_bounds CHECK (applied_amount >= 0 AND applied_amount <= amount)');
            DB::statement('ALTER TABLE student_fee_advances ADD CONSTRAINT chk_advance_refund_bounds CHECK (refunded_amount >= 0 AND (applied_amount + refunded_amount) <= amount)');
            DB::statement("ALTER TABLE student_fee_advances ADD CONSTRAINT chk_advance_status CHECK (status IN ('unapplied', 'partially_applied', 'fully_applied', 'refunded', 'reversed'))");

            DB::statement('ALTER TABLE student_fee_advance_allocations ADD CONSTRAINT chk_advance_alloc_amt_pos CHECK (allocated_amount > 0)');
            DB::statement("ALTER TABLE student_fee_advance_allocations ADD CONSTRAINT chk_advance_alloc_status CHECK (status IN ('active', 'reversed'))");

            // Payment-Source Idempotency Constraint on Advances
            DB::statement('
                CREATE UNIQUE INDEX uk_advance_payment_source 
                ON student_fee_advances (tenant_id, fee_payment_id, target_billing_period, fee_category_id) 
                WHERE fee_payment_id IS NOT NULL
            ');

            // FIFO Index for Deterministic Advance Allocation
            DB::statement('
                CREATE INDEX idx_advances_fifo 
                ON student_fee_advances (tenant_id, student_id, target_billing_period, fee_category_id, receipt_date, id)
                WHERE status IN (\'unapplied\', \'partially_applied\')
            ');

            // Partial unique index for active item-level allocations
            DB::statement("
                CREATE UNIQUE INDEX uk_advance_alloc_item_active 
                ON student_fee_advance_allocations (tenant_id, student_fee_advance_id, fee_invoice_item_id)
                WHERE fee_invoice_item_id IS NOT NULL AND status = 'active'
            ");

            // Partial unique index for active invoice-level allocations
            DB::statement("
                CREATE UNIQUE INDEX uk_advance_alloc_inv_active 
                ON student_fee_advance_allocations (tenant_id, student_fee_advance_id, fee_invoice_id)
                WHERE fee_invoice_item_id IS NULL AND status = 'active'
            ");

            // Zero-Drift Trigger: Automatically sync applied_amount and status from allocation ledger
            DB::unprepared("
                CREATE OR REPLACE FUNCTION trg_fn_sync_advance_applied_amount()
                RETURNS TRIGGER AS $$
                DECLARE
                    v_advance_id UUID;
                    v_active_applied NUMERIC(14, 2);
                BEGIN
                    v_advance_id := COALESCE(NEW.student_fee_advance_id, OLD.student_fee_advance_id);
                    
                    -- Acquire row lock on parent advance row to prevent calculation races
                    PERFORM 1 FROM student_fee_advances WHERE id = v_advance_id FOR UPDATE;

                    -- Authoritative sum of active allocations
                    SELECT COALESCE(SUM(allocated_amount), 0.00) INTO v_active_applied
                    FROM student_fee_advance_allocations
                    WHERE student_fee_advance_id = v_advance_id AND status = 'active';

                    -- Atomically update parent advance summary
                    UPDATE student_fee_advances
                    SET applied_amount = v_active_applied,
                        status = CASE 
                            WHEN (amount - v_active_applied - refunded_amount) <= 0 THEN 'fully_applied'
                            WHEN v_active_applied > 0 THEN 'partially_applied'
                            ELSE 'unapplied'
                        END,
                        updated_at = NOW()
                    WHERE id = v_advance_id;

                    RETURN COALESCE(NEW, OLD);
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_sync_advance_applied_amount ON student_fee_advance_allocations;
                CREATE TRIGGER trg_sync_advance_applied_amount
                    AFTER INSERT OR UPDATE OR DELETE ON student_fee_advance_allocations
                    FOR EACH ROW
                    EXECUTE FUNCTION trg_fn_sync_advance_applied_amount();
            ");

            foreach (['student_credit_wallets', 'student_credit_transactions', 'student_fee_advances', 'student_fee_advance_allocations'] as $table) {
                DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO educore_app");
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("
                    CREATE POLICY {$table}_tenant_isolation ON {$table}
                        FOR ALL
                        TO educore_app
                        USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                        WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_sync_advance_applied_amount ON student_fee_advance_allocations");
            DB::unprepared("DROP FUNCTION IF EXISTS trg_fn_sync_advance_applied_amount");
            DB::statement("DROP INDEX IF EXISTS uk_advance_alloc_inv_active");
            DB::statement("DROP INDEX IF EXISTS uk_advance_alloc_item_active");
            DB::statement("DROP INDEX IF EXISTS idx_advances_fifo");
            DB::statement("DROP INDEX IF EXISTS uk_advance_payment_source");

            foreach (['student_fee_advance_allocations', 'student_fee_advances', 'student_credit_transactions', 'student_credit_wallets'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('student_fee_advance_allocations');
        Schema::dropIfExists('student_fee_advances');
        Schema::dropIfExists('student_credit_transactions');
        Schema::dropIfExists('student_credit_wallets');
    }
};

