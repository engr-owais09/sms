<?php

namespace App\Console\Commands;

use App\Modules\SuperAdmin\Models\TenantSubscription;
use Illuminate\Console\Command;

class ProcessSubscriptionLifecycleCommand extends Command
{
    protected $signature = 'educore:process-subscriptions {--grace-days=7 : Days after past_due before suspension}';

    protected $description = 'Automate trial expiry, past-due grace period, and suspension lifecycle';

    public function handle(): int
    {
        $now = now();
        $graceDays = max(1, (int) $this->option('grace-days'));

        $toPastDue = TenantSubscription::query()
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->update(['status' => 'past_due']);

        $expiredGrace = TenantSubscription::with('tenant')
            ->where('status', 'past_due')
            ->where('updated_at', '<', $now->copy()->subDays($graceDays))
            ->get();

        $suspended = 0;
        foreach ($expiredGrace as $sub) {
            $sub->update(['status' => 'suspended']);
            $sub->tenant?->update(['status' => 'suspended']);
            $suspended++;
        }

        $this->info("Transitioned {$toPastDue} trial(s) to past_due and suspended {$suspended} overdue tenant(s).");

        return self::SUCCESS;
    }
}
