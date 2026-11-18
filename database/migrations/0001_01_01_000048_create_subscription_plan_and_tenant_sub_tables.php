<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('billing_cycle', 20)->default('monthly'); // monthly, yearly
            $table->decimal('price_pkr', 12, 2)->default(0.00);
            $table->decimal('per_student_rate_pkr', 10, 2)->default(0.00);
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_students')->default(200);
            $table->unsignedInteger('max_staff')->default(25);
            $table->jsonb('features_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 20)->default('trial'); // trial, active, past_due, suspended, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->unsignedInteger('custom_max_branches')->nullable();
            $table->unsignedInteger('custom_max_students')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_plans ADD CONSTRAINT chk_sub_plan_cycle CHECK (billing_cycle IN ('monthly', 'yearly'))");
            DB::statement('ALTER TABLE subscription_plans ADD CONSTRAINT chk_sub_plan_price CHECK (price_pkr >= 0 AND per_student_rate_pkr >= 0)');
            DB::statement("ALTER TABLE tenant_subscriptions ADD CONSTRAINT chk_tenant_sub_status CHECK (status IN ('trial', 'active', 'past_due', 'suspended', 'cancelled'))");

            DB::statement("CREATE UNIQUE INDEX uk_tenant_active_sub ON tenant_subscriptions (tenant_id) WHERE status IN ('trial', 'active', 'past_due')");

            DB::statement('GRANT SELECT ON subscription_plans TO educore_app');

            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON tenant_subscriptions TO educore_app');
            DB::statement('ALTER TABLE tenant_subscriptions ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE tenant_subscriptions FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_subscriptions_tenant_isolation ON tenant_subscriptions');
            DB::statement("
                CREATE POLICY tenant_subscriptions_tenant_isolation ON tenant_subscriptions
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
            DB::statement('DROP INDEX IF EXISTS uk_tenant_active_sub');
            DB::statement('DROP POLICY IF EXISTS tenant_subscriptions_tenant_isolation ON tenant_subscriptions');
            DB::statement('ALTER TABLE tenant_subscriptions NO FORCE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE tenant_subscriptions DISABLE ROW LEVEL SECURITY');
        }

        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
