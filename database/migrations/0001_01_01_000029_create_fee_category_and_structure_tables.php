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
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('category_scope', 30)->default('both'); // 'school', 'academy', 'both'
            $table->foreignUuid('default_revenue_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignUuid('unearned_liability_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->boolean('is_refundable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->foreignUuid('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignUuid('academy_course_id')->nullable()->constrained('academy_courses')->nullOnDelete();
            $table->string('name', 100);
            $table->string('frequency', 30)->default('monthly'); // 'monthly', 'one_time', 'annual', 'term', 'installment'
            $table->string('currency', 10)->default('PKR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('fee_structure_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fee_structure_id')->constrained('fee_structures')->cascadeOnDelete();
            $table->foreignUuid('fee_category_id')->constrained('fee_categories')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(['fee_structure_id', 'fee_category_id']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE fee_categories ADD CONSTRAINT chk_fee_cat_scope CHECK (category_scope IN ('school', 'academy', 'both'))");
            DB::statement("ALTER TABLE fee_structures ADD CONSTRAINT chk_fee_struct_freq CHECK (frequency IN ('monthly', 'one_time', 'annual', 'term', 'installment'))");
            DB::statement("ALTER TABLE fee_structure_items ADD CONSTRAINT chk_fee_struct_amt_positive CHECK (amount >= 0)");

            foreach (['fee_categories', 'fee_structures', 'fee_structure_items'] as $table) {
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
            foreach (['fee_structure_items', 'fee_structures', 'fee_categories'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_categories');
    }
};

