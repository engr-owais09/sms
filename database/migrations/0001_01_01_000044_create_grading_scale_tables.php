<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('scale_type', 20)->default('percentage'); // percentage, gpa
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name'], 'uk_grading_scales_name');
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('grading_scale_bands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('grading_scale_id')->constrained('grading_scales')->cascadeOnDelete();
            $table->string('grade_letter', 10);
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('max_percentage', 5, 2);
            $table->decimal('gpa_points', 4, 2)->nullable();
            $table->string('remarks', 100)->nullable();
            $table->boolean('is_passing')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'grading_scale_id', 'grade_letter'], 'uk_grading_band_letter');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE grading_scales ADD CONSTRAINT chk_grading_scale_type CHECK (scale_type IN ('percentage', 'gpa'))");
            DB::statement('ALTER TABLE grading_scale_bands ADD CONSTRAINT chk_band_range CHECK (min_percentage >= 0 AND max_percentage <= 100 AND max_percentage >= min_percentage)');

            foreach (['grading_scales', 'grading_scale_bands'] as $table) {
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
            foreach (['grading_scale_bands', 'grading_scales'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('grading_scale_bands');
        Schema::dropIfExists('grading_scales');
    }
};
