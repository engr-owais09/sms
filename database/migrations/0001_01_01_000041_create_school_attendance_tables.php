<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('attendance_mode', 20)->default('daily'); // daily, period
            $table->foreignUuid('school_timetable_entry_id')->nullable()->constrained('school_timetable_entries')->nullOnDelete();
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft, submitted, locked
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'school_section_id', 'attendance_date']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('school_attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('school_attendance_session_id')->constrained('school_attendance_sessions')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('status', 20)->default('present'); // present, absent, late, excused, half_day
            $table->string('remarks', 255)->nullable();
            $table->timestamp('marked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'school_attendance_session_id', 'student_id'], 'uk_school_att_rec_student');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE school_attendance_sessions ADD CONSTRAINT chk_school_att_mode CHECK (attendance_mode IN ('daily', 'period'))");
            DB::statement("ALTER TABLE school_attendance_sessions ADD CONSTRAINT chk_school_att_status CHECK (status IN ('draft', 'submitted', 'locked'))");
            DB::statement("ALTER TABLE school_attendance_records ADD CONSTRAINT chk_school_att_rec_status CHECK (status IN ('present', 'absent', 'late', 'excused', 'half_day'))");

            DB::statement("CREATE UNIQUE INDEX uk_school_att_daily ON school_attendance_sessions (tenant_id, school_section_id, attendance_date) WHERE attendance_mode = 'daily'");
            DB::statement("CREATE UNIQUE INDEX uk_school_att_period ON school_attendance_sessions (tenant_id, school_timetable_entry_id, attendance_date) WHERE attendance_mode = 'period' AND school_timetable_entry_id IS NOT NULL");

            foreach (['school_attendance_sessions', 'school_attendance_records'] as $table) {
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
            DB::statement('DROP INDEX IF EXISTS uk_school_att_daily');
            DB::statement('DROP INDEX IF EXISTS uk_school_att_period');

            foreach (['school_attendance_records', 'school_attendance_sessions'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('school_attendance_records');
        Schema::dropIfExists('school_attendance_sessions');
    }
};
