<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tenant_subscription_id')->constrained('tenant_subscriptions')->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->decimal('base_amount_pkr', 12, 2);
            $table->decimal('overage_amount_pkr', 12, 2)->default(0.00);
            $table->decimal('discount_amount_pkr', 12, 2)->default(0.00);
            $table->decimal('total_amount_pkr', 12, 2);
            $table->unsignedInteger('active_student_count')->default(0);
            $table->date('due_date');
            $table->string('status', 20)->default('unpaid'); // unpaid, paid, void
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tenant_subscription_id', 'billing_period_start'], 'uk_sub_inv_period');
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();
            $table->string('payment_reference', 80)->unique();
            $table->string('payment_method', 30)->default('1bill'); // bank_transfer, 1bill, jazzcash, easypaisa, card
            $table->decimal('amount_pkr', 12, 2);
            $table->timestamp('paid_at')->useCurrent();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_invoices ADD CONSTRAINT chk_sub_inv_status CHECK (status IN ('unpaid', 'paid', 'void'))");
            DB::statement('ALTER TABLE subscription_invoices ADD CONSTRAINT chk_sub_inv_total CHECK (total_amount_pkr >= 0)');
            DB::statement("ALTER TABLE subscription_payments ADD CONSTRAINT chk_sub_pay_method CHECK (payment_method IN ('bank_transfer', '1bill', 'jazzcash', 'easypaisa', 'card'))");
            DB::statement('ALTER TABLE subscription_payments ADD CONSTRAINT chk_sub_pay_amt_pos CHECK (amount_pkr > 0)');

            foreach (['subscription_invoices', 'subscription_payments'] as $table) {
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

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['subscription_payments', 'subscription_invoices'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');
    }
};
