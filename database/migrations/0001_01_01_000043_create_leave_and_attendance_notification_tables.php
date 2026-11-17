<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('leave_type', 30)->default('sick'); // sick, casual, family, other
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id', 'status']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('attendance_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('source_type', 30); // school_attendance, academy_attendance
            $table->uuid('source_session_id');
            $table->date('attendance_date');
            $table->string('channel', 20)->default('sms'); // sms, whatsapp, email
            $table->string('recipient_address', 150);
            $table->string('status', 20)->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->string('error_message', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'source_type', 'source_session_id', 'student_id', 'channel'],
                'uk_att_notif_idempotent'
            );
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE student_leave_requests ADD CONSTRAINT chk_leave_dates CHECK (end_date >= start_date)');
            DB::statement("ALTER TABLE student_leave_requests ADD CONSTRAINT chk_leave_type CHECK (leave_type IN ('sick', 'casual', 'family', 'other'))");
            DB::statement("ALTER TABLE student_leave_requests ADD CONSTRAINT chk_leave_status CHECK (status IN ('pending', 'approved', 'rejected'))");

            DB::statement("ALTER TABLE attendance_notifications ADD CONSTRAINT chk_att_notif_source CHECK (source_type IN ('school_attendance', 'academy_attendance'))");
            DB::statement("ALTER TABLE attendance_notifications ADD CONSTRAINT chk_att_notif_channel CHECK (channel IN ('sms', 'whatsapp', 'email'))");
            DB::statement("ALTER TABLE attendance_notifications ADD CONSTRAINT chk_att_notif_status CHECK (status IN ('pending', 'sent', 'failed'))");

            foreach (['student_leave_requests', 'attendance_notifications'] as $table) {
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
            foreach (['attendance_notifications', 'student_leave_requests'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('attendance_notifications');
        Schema::dropIfExists('student_leave_requests');
    }
};
