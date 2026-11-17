<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_terms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('grading_scale_id')->nullable()->constrained('grading_scales')->nullOnDelete();
            $table->string('name', 120);
            $table->string('term_type', 30)->default('midterm'); // unit_test, midterm, final, annual
            $table->decimal('weightage_percentage', 5, 2)->default(100.00);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('draft'); // draft, scheduled, marks_entry, published, locked
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'academic_session_id', 'name'], 'uk_exam_terms_name');
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('exam_papers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('exam_term_id')->constrained('exam_terms')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('max_marks', 6, 2)->default(100.00);
            $table->decimal('passing_marks', 6, 2)->default(40.00);
            $table->foreignUuid('campus_room_id')->nullable()->constrained('campus_rooms')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'exam_term_id', 'school_class_id', 'subject_id'], 'uk_exam_papers_subject');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE exam_terms ADD CONSTRAINT chk_exam_term_type CHECK (term_type IN ('unit_test', 'midterm', 'final', 'annual'))");
            DB::statement("ALTER TABLE exam_terms ADD CONSTRAINT chk_exam_term_status CHECK (status IN ('draft', 'scheduled', 'marks_entry', 'published', 'locked'))");
            DB::statement('ALTER TABLE exam_terms ADD CONSTRAINT chk_exam_term_dates CHECK (end_date >= start_date)');
            DB::statement('ALTER TABLE exam_terms ADD CONSTRAINT chk_exam_term_weight CHECK (weightage_percentage > 0 AND weightage_percentage <= 100)');
            DB::statement('ALTER TABLE exam_papers ADD CONSTRAINT chk_exam_paper_marks CHECK (max_marks > 0 AND passing_marks >= 0 AND passing_marks <= max_marks)');

            foreach (['exam_terms', 'exam_papers'] as $table) {
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
            foreach (['exam_papers', 'exam_terms'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('exam_papers');
        Schema::dropIfExists('exam_terms');
    }
};
