<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('academy_batch_enrollment_id')->nullable()->constrained('academy_batch_enrollments')->nullOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->integer('number_of_installments');
            $table->string('status', 30)->default('active'); // 'active', 'completed', 'cancelled'
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('installment_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('installment_plan_id')->constrained('installment_plans')->cascadeOnDelete();
            $table->string('milestone_name', 100);
            $table->integer('sequence_number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('pending'); // 'pending', 'invoiced', 'paid'
            $table->timestamps();

            $table->unique(['installment_plan_id', 'sequence_number']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE installment_plans ADD CONSTRAINT chk_inst_plan_total_pos CHECK (total_amount > 0)");
            DB::statement("ALTER TABLE installment_plans ADD CONSTRAINT chk_inst_plan_num_pos CHECK (number_of_installments > 0)");
            DB::statement("ALTER TABLE installment_plans ADD CONSTRAINT chk_inst_plan_status CHECK (status IN ('active', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE installment_milestones ADD CONSTRAINT chk_inst_milestone_amt_pos CHECK (amount > 0)");
            DB::statement("ALTER TABLE installment_milestones ADD CONSTRAINT chk_inst_milestone_status CHECK (status IN ('pending', 'invoiced', 'paid'))");

            foreach (['installment_plans', 'installment_milestones'] as $table) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['installment_milestones', 'installment_plans'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('installment_milestones');
        Schema::dropIfExists('installment_plans');
    }
};

