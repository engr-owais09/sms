<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_timetable_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignUuid('school_section_id')->constrained('school_sections')->cascadeOnDelete();
            $table->foreignUuid('school_period_id')->constrained('school_periods')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('campus_room_id')->nullable()->constrained('campus_rooms')->nullOnDelete();
            $table->unsignedSmallInteger('day_of_week'); // 1 = Monday .. 7 = Sunday
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'academic_session_id', 'school_section_id']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE school_timetable_entries ADD CONSTRAINT chk_tt_day_of_week CHECK (day_of_week BETWEEN 1 AND 7)');

            DB::statement('CREATE UNIQUE INDEX uk_tt_section_slot ON school_timetable_entries (tenant_id, academic_session_id, school_section_id, day_of_week, school_period_id) WHERE is_active = true');
            DB::statement('CREATE UNIQUE INDEX uk_tt_teacher_slot ON school_timetable_entries (tenant_id, academic_session_id, teacher_id, day_of_week, school_period_id) WHERE is_active = true AND teacher_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX uk_tt_room_slot ON school_timetable_entries (tenant_id, academic_session_id, campus_room_id, day_of_week, school_period_id) WHERE is_active = true AND campus_room_id IS NOT NULL');

            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON school_timetable_entries TO educore_app');
            DB::statement('ALTER TABLE school_timetable_entries ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE school_timetable_entries FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS school_timetable_entries_tenant_isolation ON school_timetable_entries');
            DB::statement("
                CREATE POLICY school_timetable_entries_tenant_isolation ON school_timetable_entries
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
            DB::statement('DROP INDEX IF EXISTS uk_tt_section_slot');
            DB::statement('DROP INDEX IF EXISTS uk_tt_teacher_slot');
            DB::statement('DROP INDEX IF EXISTS uk_tt_room_slot');

            DB::statement('DROP POLICY IF EXISTS school_timetable_entries_tenant_isolation ON school_timetable_entries');
            DB::statement('ALTER TABLE school_timetable_entries NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE school_timetable_entries DISABLE ROW LEVEL SECURITY');
        }

        Schema::dropIfExists('school_timetable_entries');
    }
};
