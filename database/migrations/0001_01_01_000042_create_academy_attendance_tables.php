<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('academy_batch_id')->constrained('academy_batches')->cascadeOnDelete();
            $table->foreignUuid('academy_batch_schedule_id')->nullable()->constrained('academy_batch_schedules')->nullOnDelete();
            $table->date('session_date');
            $table->string('topic_covered', 255)->nullable();
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft, submitted, locked
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'academy_batch_id', 'session_date']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('academy_attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('academy_attendance_session_id')->constrained('academy_attendance_sessions')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('status', 20)->default('present'); // present, absent, late, excused
            $table->string('remarks', 255)->nullable();
            $table->timestamp('marked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'academy_attendance_session_id', 'student_id'], 'uk_acad_att_rec_student');
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE academy_attendance_sessions ADD CONSTRAINT chk_acad_att_status CHECK (status IN ('draft', 'submitted', 'locked'))");
            DB::statement("ALTER TABLE academy_attendance_records ADD CONSTRAINT chk_acad_att_rec_status CHECK (status IN ('present', 'absent', 'late', 'excused'))");

            DB::statement('CREATE UNIQUE INDEX uk_acad_att_slot ON academy_attendance_sessions (tenant_id, academy_batch_id, academy_batch_schedule_id, session_date) WHERE academy_batch_schedule_id IS NOT NULL');

            foreach (['academy_attendance_sessions', 'academy_attendance_records'] as $table) {
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
            DB::statement('DROP INDEX IF EXISTS uk_acad_att_slot');

            foreach (['academy_attendance_records', 'academy_attendance_sessions'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('academy_attendance_records');
        Schema::dropIfExists('academy_attendance_sessions');
    }
};
