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
        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedBigInteger('cashier_user_id');
            $table->string('session_code', 50);
            $table->string('status', 30)->default('open'); // 'open', 'closed', 'reconciled'
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 12, 2)->default(0.00);
            $table->decimal('expected_cash_balance', 12, 2)->default(0.00);
            $table->decimal('actual_counted_cash', 12, 2)->nullable();
            $table->decimal('variance_amount', 12, 2)->nullable();
            $table->jsonb('denominations')->nullable();
            $table->text('closing_notes')->nullable();
            $table->unsignedBigInteger('reconciled_by_user_id')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignUuid('closing_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->foreign('cashier_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reconciled_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'session_code']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE cashier_sessions ADD CONSTRAINT chk_cashier_session_status CHECK (status IN ('open', 'closed', 'reconciled'))");
            DB::statement('ALTER TABLE cashier_sessions ADD CONSTRAINT chk_cashier_session_float CHECK (opening_float >= 0)');

            // Exactly ONE open session allowed per cashier at any time
            DB::statement("
                CREATE UNIQUE INDEX uk_active_cashier_session 
                ON cashier_sessions (tenant_id, cashier_user_id) 
                WHERE status = 'open'
            ");

            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON cashier_sessions TO educore_app");
            DB::statement("ALTER TABLE cashier_sessions ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE cashier_sessions FORCE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS cashier_sessions_tenant_isolation ON cashier_sessions");
            DB::statement("
                CREATE POLICY cashier_sessions_tenant_isolation ON cashier_sessions
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
            DB::statement("DROP INDEX IF EXISTS uk_active_cashier_session");
            DB::statement("DROP POLICY IF EXISTS cashier_sessions_tenant_isolation ON cashier_sessions");
            DB::statement("ALTER TABLE cashier_sessions NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE cashier_sessions DISABLE ROW LEVEL SECURITY");
        }

        Schema::dropIfExists('cashier_sessions');
    }
};

