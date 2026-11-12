<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('account_code', 50);
            $table->string('account_name', 150);
            $table->string('account_type', 30); // 'asset', 'liability', 'equity', 'revenue', 'expense'
            $table->string('subledger_type', 50)->nullable(); // 'student_fee_advances', 'student_credit_wallets', 'accounts_receivable'
            $table->boolean('is_system_controlled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('parent_account_id')->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->unique(['tenant_id', 'account_code'], 'uk_coa_tenant_code');
            $table->unique(['tenant_id', 'id'], 'uk_coa_tenant_id');
        });

        // Add self-referential foreign key after table creation
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreign('parent_account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->string('entry_number', 100);
            $table->date('entry_date');
            $table->uuid('accounting_period_id')->nullable();
            $table->string('status', 20)->default('draft'); // 'draft', 'posted', 'reversed'
            $table->string('source_type', 50); // 'manual', 'invoice_accrual', 'payment_receipt', 'advance_receipt', 'advance_settlement', 'reversal', etc.
            $table->uuid('source_id')->nullable();
            $table->uuid('reverses_entry_id')->nullable();
            $table->uuid('reversed_by_entry_id')->nullable();
            $table->timestampTz('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by_user_id')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('restrict');
            $table->foreign('accounting_period_id')->references('id')->on('accounting_periods')->onDelete('restrict');
            $table->foreign('posted_by_user_id')->references('id')->on('users')->onDelete('restrict');

            $table->unique(['tenant_id', 'entry_number'], 'uk_je_tenant_number');
            $table->unique(['tenant_id', 'id'], 'uk_je_tenant_id');
            $table->unique(['reversed_by_entry_id'], 'uk_je_reversed_by');
        });

        // Add self-referential reversal foreign keys after table creation
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('reverses_entry_id')->references('id')->on('journal_entries')->onDelete('restrict');
            $table->foreign('reversed_by_entry_id')->references('id')->on('journal_entries')->onDelete('restrict');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');
            $table->string('entry_type', 10); // 'debit', 'credit'
            $table->decimal('amount', 14, 2);
            $table->smallInteger('line_number');
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('restrict');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');
            $table->unique(['tenant_id', 'journal_entry_id', 'line_number'], 'uk_jel_seq');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE chart_of_accounts ADD CONSTRAINT chk_coa_account_type CHECK (account_type IN ('asset', 'liability', 'equity', 'revenue', 'expense'))");
            DB::statement("ALTER TABLE journal_entries ADD CONSTRAINT chk_je_status CHECK (status IN ('draft', 'posted', 'reversed'))");
            DB::statement("ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_entry_type CHECK (entry_type IN ('debit', 'credit'))");
            DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_amount CHECK (amount > 0)');

            foreach (['chart_of_accounts', 'journal_entries', 'journal_entry_lines'] as $table) {
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
            foreach (['journal_entry_lines', 'journal_entries', 'chart_of_accounts'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
