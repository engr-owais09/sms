<?php

namespace App\Console\Commands;

use App\Modules\Institute\Services\TenantOffboardingService;
use Illuminate\Console\Command;

class PurgeOffboardedTenantsCommand extends Command
{
    protected $signature = 'educore:purge-offboarded-tenants {--retention-days=30 : Minimum days since soft-delete}';

    protected $description = 'Hard-delete soft-deleted tenants past retention period unless legal_hold is set';

    public function handle(TenantOffboardingService $service): int
    {
        $count = $service->purgeExpired((int) $this->option('retention-days'));
        $this->info("Purged {$count} offboarded tenant(s).");

        return self::SUCCESS;
    }
}
