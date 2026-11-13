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
        Schema::create('expense_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('cashier_session_id')->nullable()->constrained('cashier_sessions')->nullOnDelete();
            $table->string('voucher_number', 50);
            $table->date('voucher_date');
            $table->string('payee_name', 150);
            $table->string('payment_method', 30)->default('cash'); // 'cash', 'bank_transfer', 'cheque'
            $table->foreignUuid('debit_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignUuid('credit_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('posted'); // 'draft', 'posted', 'voided'
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'voucher_number']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE expense_vouchers ADD CONSTRAINT chk_expense_amount_pos CHECK (amount > 0)');
            DB::statement("ALTER TABLE expense_vouchers ADD CONSTRAINT chk_expense_status CHECK (status IN ('draft', 'posted', 'voided'))");
            DB::statement("ALTER TABLE expense_vouchers ADD CONSTRAINT chk_expense_method CHECK (payment_method IN ('cash', 'bank_transfer', 'cheque'))");

            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON expense_vouchers TO educore_app");
            DB::statement("ALTER TABLE expense_vouchers ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE expense_vouchers FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS expense_vouchers_tenant_isolation ON expense_vouchers");
            DB::statement("
                CREATE POLICY expense_vouchers_tenant_isolation ON expense_vouchers
                    FOR ALL
                    TO educore_app
                    USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP POLICY IF EXISTS expense_vouchers_tenant_isolation ON expense_vouchers");
            DB::statement("ALTER TABLE expense_vouchers NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE expense_vouchers DISABLE ROW LEVEL SECURITY");
        }

        Schema::dropIfExists('expense_vouchers');
    }
};

