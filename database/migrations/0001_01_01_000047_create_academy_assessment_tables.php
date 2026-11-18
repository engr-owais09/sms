<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('academy_batch_id')->constrained('academy_batches')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('assessment_type', 30)->default('chapter_test'); // chapter_test, mock_exam, past_paper, final_test
            $table->date('assessment_date');
            $table->decimal('max_marks', 6, 2)->default(100.00);
            $table->decimal('passing_marks', 6, 2)->default(40.00);
            $table->string('status', 20)->default('draft'); // draft, published
            $table->timestamps();

            $table->index(['tenant_id', 'academy_batch_id']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('academy_assessment_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('academy_assessment_id')->constrained('academy_assessments')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2)->default(0.00);
            $table->boolean('is_absent')->default(false);
            $table->decimal('percentage', 5, 2)->default(0.00);
            $table->unsignedInteger('rank')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'academy_assessment_id', 'student_id'], 'uk_acad_score_student');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE academy_assessments ADD CONSTRAINT chk_acad_assess_type CHECK (assessment_type IN ('chapter_test', 'mock_exam', 'past_paper', 'final_test'))");
            DB::statement("ALTER TABLE academy_assessments ADD CONSTRAINT chk_acad_assess_status CHECK (status IN ('draft', 'published'))");
            DB::statement('ALTER TABLE academy_assessments ADD CONSTRAINT chk_acad_assess_marks CHECK (max_marks > 0 AND passing_marks >= 0 AND passing_marks <= max_marks)');
            DB::statement('ALTER TABLE academy_assessment_scores ADD CONSTRAINT chk_acad_score_non_neg CHECK (marks_obtained >= 0)');

            foreach (['academy_assessments', 'academy_assessment_scores'] as $table) {
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
            foreach (['academy_assessment_scores', 'academy_assessments'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('academy_assessment_scores');
        Schema::dropIfExists('academy_assessments');
    }
};
