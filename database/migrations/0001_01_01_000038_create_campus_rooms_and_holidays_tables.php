<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('room_code', 30);
            $table->string('name', 100);
            $table->string('room_type', 30)->default('classroom'); // classroom, lab, auditorium
            $table->unsignedInteger('capacity')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'branch_id', 'room_code'], 'uk_campus_rooms_code');
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('academic_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('title', 150);
            $table->date('holiday_date');
            $table->string('holiday_type', 30)->default('gazetted'); // gazetted, vacation, emergency
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'holiday_date']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE campus_rooms ADD CONSTRAINT chk_room_type CHECK (room_type IN ('classroom', 'lab', 'auditorium'))");
            DB::statement('ALTER TABLE campus_rooms ADD CONSTRAINT chk_room_capacity_pos CHECK (capacity > 0)');
            DB::statement("ALTER TABLE academic_holidays ADD CONSTRAINT chk_holiday_type CHECK (holiday_type IN ('gazetted', 'vacation', 'emergency'))");

            DB::statement('CREATE UNIQUE INDEX uk_holiday_branch_date ON academic_holidays (tenant_id, branch_id, holiday_date) WHERE branch_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX uk_holiday_tenant_date ON academic_holidays (tenant_id, holiday_date) WHERE branch_id IS NULL');

            foreach (['campus_rooms', 'academic_holidays'] as $table) {
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
            DB::statement('DROP INDEX IF EXISTS uk_holiday_branch_date');
            DB::statement('DROP INDEX IF EXISTS uk_holiday_tenant_date');

            foreach (['academic_holidays', 'campus_rooms'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('academic_holidays');
        Schema::dropIfExists('campus_rooms');
    }
};
