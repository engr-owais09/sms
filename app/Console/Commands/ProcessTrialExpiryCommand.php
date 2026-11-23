<?php

namespace App\Console\Commands;

use App\Modules\SuperAdmin\Models\TenantSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessTrialExpiryCommand extends Command
{
    protected $signature = 'educore:process-trial-expiry';

    protected $description = 'Idempotently transition expired trial subscriptions to past_due';

    public function handle(): int
    {
        $updated = TenantSubscription::query()
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->update(['status' => 'past_due']);

        Log::info('Processed trial expiry sweep.', ['transitioned_to_past_due' => $updated]);
        $this->info("Transitioned {$updated} expired trial subscription(s) to past_due.");

        return self::SUCCESS;
    }
}
