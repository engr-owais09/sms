<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('event', 80);
            $table->string('auditable_type', 120)->nullable();
            $table->string('auditable_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE "tenant_audit_logs" ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE "tenant_audit_logs" FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON "tenant_audit_logs"');
            DB::statement('
                CREATE POLICY tenant_isolation_policy ON "tenant_audit_logs"
                USING ("tenant_id" = NULLIF(current_setting(\'app.current_tenant_id\', true), \'\')::uuid)
                WITH CHECK ("tenant_id" = NULLIF(current_setting(\'app.current_tenant_id\', true), \'\')::uuid)
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS tenant_isolation_policy ON "tenant_audit_logs"');
        }
        Schema::dropIfExists('tenant_audit_logs');
    }
};
