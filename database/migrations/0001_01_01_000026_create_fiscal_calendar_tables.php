<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // 'open', 'closed', 'locked'
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->unique(['tenant_id', 'name'], 'uk_fiscal_years_tenant_name');
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('fiscal_year_id');
            $table->smallInteger('period_number');
            $table->string('period_name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // 'open', 'closed', 'locked'
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('restrict');
            $table->unique(['tenant_id', 'fiscal_year_id', 'period_number'], 'uk_acc_periods_seq');
            $table->unique(['tenant_id', 'fiscal_year_id', 'period_name'], 'uk_acc_periods_name');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE fiscal_years ADD CONSTRAINT chk_fiscal_years_dates CHECK (end_date >= start_date)');
            DB::statement('ALTER TABLE accounting_periods ADD CONSTRAINT chk_acc_periods_dates CHECK (end_date >= start_date)');
            DB::statement("ALTER TABLE fiscal_years ADD CONSTRAINT chk_fiscal_years_status CHECK (status IN ('open', 'closed', 'locked'))");
            DB::statement("ALTER TABLE accounting_periods ADD CONSTRAINT chk_acc_periods_status CHECK (status IN ('open', 'closed', 'locked'))");

            foreach (['fiscal_years', 'accounting_periods'] as $table) {
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
            foreach (['accounting_periods', 'fiscal_years'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
    }
};

