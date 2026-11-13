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
        Schema::create('fee_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('cashier_session_id')->nullable()->constrained('cashier_sessions')->nullOnDelete();
            $table->unsignedBigInteger('received_by_user_id');
            $table->string('receipt_number', 50);
            $table->date('payment_date');
            $table->string('payment_method', 30)->default('cash'); // 'cash', 'bank_transfer', 'cheque', 'pos_card', '1bill', 'online'
            $table->decimal('amount_tendered', 12, 2);
            $table->decimal('allocated_to_invoices', 12, 2)->default(0.00);
            $table->decimal('allocated_to_advances', 12, 2)->default(0.00);
            $table->decimal('credited_to_wallet', 12, 2)->default(0.00);
            $table->string('instrument_reference', 100)->nullable();
            $table->string('status', 30)->default('posted'); // 'draft', 'posted', 'voided', 'partially_refunded', 'fully_refunded'
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('received_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tenant_id', 'receipt_number']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('fee_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_payment_id')->constrained('fee_payments')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_item_id')->nullable()->constrained('fee_invoice_items')->nullOnDelete();
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fee_payments ADD CONSTRAINT chk_payment_amount_pos CHECK (amount_tendered > 0)');
            DB::statement('ALTER TABLE fee_payments ADD CONSTRAINT chk_payment_allocations_non_neg CHECK (allocated_to_invoices >= 0 AND allocated_to_advances >= 0 AND credited_to_wallet >= 0)');
            DB::statement('ALTER TABLE fee_payments ADD CONSTRAINT chk_payment_equilibrium CHECK (amount_tendered = (allocated_to_invoices + allocated_to_advances + credited_to_wallet))');
            DB::statement("ALTER TABLE fee_payments ADD CONSTRAINT chk_payment_status CHECK (status IN ('draft', 'posted', 'voided', 'partially_refunded', 'fully_refunded'))");
            DB::statement("ALTER TABLE fee_payments ADD CONSTRAINT chk_payment_method CHECK (payment_method IN ('cash', 'bank_transfer', 'cheque', 'pos_card', '1bill', 'online'))");
            DB::statement('ALTER TABLE fee_payment_allocations ADD CONSTRAINT chk_payment_alloc_amt_pos CHECK (allocated_amount > 0)');

            foreach (['fee_payments', 'fee_payment_allocations'] as $table) {
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
            foreach (['fee_payment_allocations', 'fee_payments'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('fee_payment_allocations');
        Schema::dropIfExists('fee_payments');
    }
};

