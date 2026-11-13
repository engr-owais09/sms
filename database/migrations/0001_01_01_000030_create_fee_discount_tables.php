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
        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50);
            $table->string('discount_type', 20)->default('percentage'); // 'percentage', 'fixed_amount'
            $table->decimal('discount_value', 12, 2);
            $table->foreignUuid('contra_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignUuid('applicable_category_id')->nullable()->constrained('fee_categories')->nullOnDelete();
            $table->string('stacking_rule', 30)->default('highest_wins'); // 'highest_wins', 'compound', 'sum_capped'
            $table->decimal('max_discount_cap', 12, 2)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('student_fee_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('fee_discount_id')->constrained('fee_discounts')->cascadeOnDelete();
            $table->decimal('custom_value', 12, 2)->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 30)->default('active'); // 'active', 'suspended', 'expired'
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'student_id', 'fee_discount_id']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE fee_discounts ADD CONSTRAINT chk_discount_type CHECK (discount_type IN ('percentage', 'fixed_amount'))");
            DB::statement("ALTER TABLE fee_discounts ADD CONSTRAINT chk_discount_stacking CHECK (stacking_rule IN ('highest_wins', 'compound', 'sum_capped'))");
            DB::statement("ALTER TABLE fee_discounts ADD CONSTRAINT chk_discount_val_pos CHECK (discount_value >= 0)");
            DB::statement("ALTER TABLE student_fee_discounts ADD CONSTRAINT chk_std_disc_status CHECK (status IN ('active', 'suspended', 'expired'))");
            DB::statement("ALTER TABLE student_fee_discounts ADD CONSTRAINT chk_std_disc_dates CHECK (valid_until IS NULL OR valid_until >= valid_from)");

            foreach (['fee_discounts', 'student_fee_discounts'] as $table) {
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
            foreach (['student_fee_discounts', 'fee_discounts'] as $table) {
                DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
                DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('student_fee_discounts');
        Schema::dropIfExists('fee_discounts');
    }
};

