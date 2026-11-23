<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grant least-privilege runtime table/sequence permissions to educore_app
     * while preserving FORCE ROW LEVEL SECURITY on all tenant-owned tables.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('GRANT USAGE ON SCHEMA public TO educore_app');

        $runtimeTables = [
            'tenants',
            'branches',
            'domains',
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'roles',
            'permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
            'platform_audit_logs',
            'subscription_plans',
        ];

        foreach ($runtimeTables as $table) {
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE public.{$table} TO educore_app");
        }

        DB::statement('GRANT SELECT ON TABLE public.migrations TO educore_app');
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO educore_app');
        DB::statement('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO educore_app');

        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO educore_app');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO educore_app');

        // Narrow SECURITY DEFINER helper so IdentifyTenantBySubdomain can resolve verified custom domains
        // under educore_app before app.current_tenant_id is established, without opening cross-tenant SELECT on domains.
        DB::statement("
            CREATE OR REPLACE FUNCTION public.resolve_tenant_id_by_domain(lookup_domain text)
            RETURNS uuid
            LANGUAGE sql
            STABLE
            SECURITY DEFINER
            SET search_path = public
            AS \$\$
                SELECT tenant_id
                FROM public.domains
                WHERE domain = lookup_domain
                  AND is_verified = true
                LIMIT 1;
            \$\$;
        ");

        DB::statement('REVOKE ALL ON FUNCTION public.resolve_tenant_id_by_domain(text) FROM PUBLIC');
        DB::statement('GRANT EXECUTE ON FUNCTION public.resolve_tenant_id_by_domain(text) TO educore_app');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS public.resolve_tenant_id_by_domain(text)');
    }
};
