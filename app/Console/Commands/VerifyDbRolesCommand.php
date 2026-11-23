<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDbRolesCommand extends Command
{
    protected $signature = 'educore:verify-db-roles {--app-role=educore_app} {--migrator-role=educore_migrator}';

    protected $description = 'Verify PostgreSQL runtime app role is non-superuser and does not bypass RLS';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Skipping PostgreSQL role check (driver is not pgsql).');
            return self::SUCCESS;
        }

        $appRoleName = (string) $this->option('app-role');
        $migratorRoleName = (string) $this->option('migrator-role');

        if ($appRoleName === $migratorRoleName) {
            $this->error('Runtime app role and migrator role must be separate.');
            return self::FAILURE;
        }

        $role = DB::selectOne(
            'SELECT rolname, rolsuper, rolbypassrls, rolcanlogin FROM pg_roles WHERE rolname = ?',
            [$appRoleName]
        );

        if (!$role) {
            $this->error("Runtime role [{$appRoleName}] does not exist in PostgreSQL.");
            return self::FAILURE;
        }

        if ($role->rolsuper || $role->rolbypassrls || !$role->rolcanlogin) {
            $this->error("Role [{$appRoleName}] violates least-privilege requirements (rolsuper={$role->rolsuper}, rolbypassrls={$role->rolbypassrls}, rolcanlogin={$role->rolcanlogin}).");
            return self::FAILURE;
        }

        $this->info("Verified least-privilege runtime role [{$appRoleName}]: non-superuser, no BYPASSRLS, separate from [{$migratorRoleName}].");

        return self::SUCCESS;
    }
}
