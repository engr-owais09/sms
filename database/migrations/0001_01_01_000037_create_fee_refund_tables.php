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
        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('fee_payment_id')->constrained('fee_payments')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->nullable()->constrained('fee_invoices')->nullOnDelete();
            $table->string('refund_number', 50);
            $table->date('refund_date');
            $table->string('refund_type', 30)->default('partial'); // 'full', 'partial', 'security_deposit', 'overpayment'
            $table->string('refund_method', 30)->default('cash'); // 'cash', 'bank_transfer', 'cheque'
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('concession_clawback_amount', 12, 2)->default(0.00);
            $table->decimal('net_payout_amount', 12, 2)->nullable();
            $table->string('status', 30)->default('pending_approval'); // 'draft', 'pending_approval', 'approved', 'rejected', 'disbursed', 'voided'
            $table->text('reason');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('maker_user_id');
            $table->unsignedBigInteger('checker_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->unsignedBigInteger('disbursed_by_user_id')->nullable();
            $table->foreignUuid('cashier_session_id')->nullable()->constrained('cashier_sessions')->nullOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('maker_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('checker_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('disbursed_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'refund_number']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('fee_refund_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_refund_id')->constrained('fee_refunds')->cascadeOnDelete();
            $table->foreignUuid('fee_payment_allocation_id')->nullable()->constrained('fee_payment_allocations')->nullOnDelete();
            $table->foreignUuid('fee_invoice_item_id')->nullable()->constrained('fee_invoice_items')->nullOnDelete();
            $table->decimal('refunded_amount', 12, 2);
            $table->decimal('concession_clawback', 12, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fee_refunds ADD CONSTRAINT chk_refund_req_amt_pos CHECK (requested_amount > 0)');
            DB::statement("ALTER TABLE fee_refunds ADD CONSTRAINT chk_refund_status CHECK (status IN ('draft', 'pending_approval', 'approved', 'rejected', 'disbursed', 'voided'))");
            DB::statement("ALTER TABLE fee_refunds ADD CONSTRAINT chk_refund_method CHECK (refund_method IN ('cash', 'bank_transfer', 'cheque'))");
            // Maker-Checker Segregation of Duties Check
            DB::statement('ALTER TABLE fee_refunds ADD CONSTRAINT chk_refund_segregation_of_duties CHECK (checker_user_id IS NULL OR checker_user_id <> maker_user_id)');
            DB::statement('ALTER TABLE fee_refund_allocations ADD CONSTRAINT chk_refund_alloc_amt_pos CHECK (refunded_amount > 0)');

            foreach (['fee_refunds', 'fee_refund_allocations'] as $table) {
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
            foreach (['fee_refund_allocations', 'fee_refunds'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('fee_refund_allocations');
        Schema::dropIfExists('fee_refunds');
    }
};

