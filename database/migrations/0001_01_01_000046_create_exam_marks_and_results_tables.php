<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('exam_paper_id')->constrained('exam_papers')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2)->default(0.00);
            $table->boolean('is_absent')->default(false);
            $table->string('remarks', 255)->nullable();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft, submitted, verified
            $table->timestamps();

            $table->unique(['tenant_id', 'exam_paper_id', 'student_id'], 'uk_exam_marks_student');
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('exam_term_id')->constrained('exam_terms')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('total_max_marks', 8, 2);
            $table->decimal('total_obtained_marks', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade_letter', 10);
            $table->decimal('gpa', 4, 2)->nullable();
            $table->unsignedInteger('section_rank')->nullable();
            $table->string('result_status', 20)->default('pass'); // pass, fail, compartment
            $table->string('teacher_remarks', 255)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'exam_term_id', 'student_id'], 'uk_exam_results_student');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exam_marks ADD CONSTRAINT chk_exam_marks_non_neg CHECK (marks_obtained >= 0)');
            DB::statement("ALTER TABLE exam_marks ADD CONSTRAINT chk_exam_marks_status CHECK (status IN ('draft', 'submitted', 'verified'))");
            DB::statement("ALTER TABLE exam_results ADD CONSTRAINT chk_exam_result_status CHECK (result_status IN ('pass', 'fail', 'compartment'))");

            foreach (['exam_marks', 'exam_results'] as $table) {
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
            foreach (['exam_results', 'exam_marks'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_marks');
    }
};
