<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'subjects',
            'students',
            'guardians',
            'student_guardians',
            'student_documents',
            'academic_sessions',
            'school_classes',
            'school_sections',
            'school_class_subjects',
            'school_student_enrollments',
            'school_promotions',
            'academy_programs',
            'academy_courses',
            'academy_batches',
            'academy_batch_schedules',
            'academy_batch_enrollments',
            'academy_course_completions',
        ];

        foreach ($tables as $table) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'subjects',
            'students',
            'guardians',
            'student_guardians',
            'student_documents',
            'academic_sessions',
            'school_classes',
            'school_sections',
            'school_class_subjects',
            'school_student_enrollments',
            'school_promotions',
            'academy_programs',
            'academy_courses',
            'academy_batches',
            'academy_batch_schedules',
            'academy_batch_enrollments',
            'academy_course_completions',
        ];

        foreach ($tables as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};

