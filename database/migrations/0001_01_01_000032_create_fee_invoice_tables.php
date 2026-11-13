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
        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('installment_milestone_id')->nullable()->constrained('installment_milestones')->nullOnDelete();
            $table->foreignUuid('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignUuid('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('invoice_number', 50);
            $table->string('invoice_type', 30)->default('school_monthly'); // 'school_monthly', 'academy_installment', 'admission_fee', 'custom'
            $table->string('billing_period', 20)->nullable(); // e.g. '2026-10'
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('late_fee_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->string('status', 30)->default('unpaid'); // 'unpaid', 'partially_paid', 'paid', 'cancelled'
            $table->string('psid_1bill', 30)->nullable();
            $table->string('barcode_code128', 50)->nullable();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignUuid('fee_category_id')->constrained('fee_categories')->cascadeOnDelete();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('net_amount', 12, 2);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE fee_invoices ADD CONSTRAINT chk_invoice_status CHECK (status IN ('unpaid', 'partially_paid', 'paid', 'cancelled'))");
            DB::statement("ALTER TABLE fee_invoices ADD CONSTRAINT chk_invoice_type CHECK (invoice_type IN ('school_monthly', 'academy_installment', 'admission_fee', 'custom'))");
            DB::statement("ALTER TABLE fee_invoices ADD CONSTRAINT chk_invoice_dates CHECK (due_date >= issue_date)");
            DB::statement("ALTER TABLE fee_invoices ADD CONSTRAINT chk_invoice_amounts CHECK (total_amount >= 0 AND paid_amount >= 0 AND paid_amount <= total_amount)");
            DB::statement("ALTER TABLE fee_invoice_items ADD CONSTRAINT chk_invoice_item_net CHECK (net_amount >= 0 AND gross_amount >= discount_amount)");

            // Partial unique index for monthly idempotency (excluding cancelled invoices)
            DB::statement("
                CREATE UNIQUE INDEX uk_fee_invoice_monthly_idempotency 
                ON fee_invoices (tenant_id, student_id, billing_period, invoice_type) 
                WHERE status <> 'cancelled' AND billing_period IS NOT NULL
            ");

            foreach (['fee_invoices', 'fee_invoice_items'] as $table) {
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
            DB::statement("DROP INDEX IF EXISTS uk_fee_invoice_monthly_idempotency");

            foreach (['fee_invoice_items', 'fee_invoices'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('fee_invoice_items');
        Schema::dropIfExists('fee_invoices');
    }
};

