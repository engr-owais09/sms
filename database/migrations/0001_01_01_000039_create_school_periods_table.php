<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->string('label', 60); // e.g., "Period 1", "Lunch Break"
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_break')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'period_number'], 'uk_school_periods_number');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE school_periods ADD CONSTRAINT chk_school_period_times CHECK (end_time > start_time)');
            DB::statement('ALTER TABLE school_periods ADD CONSTRAINT chk_school_period_num_pos CHECK (period_number > 0)');

            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON school_periods TO educore_app');
            DB::statement('ALTER TABLE school_periods ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE school_periods FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS school_periods_tenant_isolation ON school_periods');
            DB::statement("
                CREATE POLICY school_periods_tenant_isolation ON school_periods
                    FOR ALL
                    TO educore_app
                    USING (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
                    WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::uuid)
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS school_periods_tenant_isolation ON school_periods');
            DB::statement('ALTER TABLE school_periods NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE school_periods DISABLE ROW LEVEL SECURITY');
        }

        Schema::dropIfExists('school_periods');
    }
};
