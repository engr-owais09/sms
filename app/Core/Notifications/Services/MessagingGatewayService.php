<?php

namespace App\Core\Notifications\Services;

use App\Core\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MessagingGatewayService
{
    /**
     * Send a notification via the configured channel (sandbox/log by default).
     */
    public function send(
        string $tenantId,
        string $channel,
        string $recipient,
        string $message,
        string $locale = 'en',
        bool $respectQuietHours = false,
        ?int $hourOverride = null
    ): NotificationDelivery {
        $hour = $hourOverride ?? (int) now()->format('G');
        if ($respectQuietHours && ($hour >= 21 || $hour < 7)) {
            return NotificationDelivery::create([
                'tenant_id' => $tenantId,
                'channel' => $channel,
                'recipient' => $recipient,
                'locale' => $locale,
                'message' => $message,
                'status' => 'skipped_quiet_hours',
                'cost_pkr' => 0,
            ]);
        }

        $providerId = 'SBX-'.strtoupper(Str::random(10));
        Log::info("Sandbox [{$channel}] message dispatched.", [
            'tenant_id' => $tenantId,
            'recipient' => $recipient,
            'provider_id' => $providerId,
        ]);

        return NotificationDelivery::create([
            'tenant_id' => $tenantId,
            'channel' => $channel,
            'recipient' => $recipient,
            'locale' => $locale,
            'message' => $message,
            'provider_id' => $providerId,
            'status' => 'sent',
            'cost_pkr' => $channel === 'sms' ? 1.50 : ($channel === 'whatsapp' ? 2.00 : 0.00),
        ]);
    }
}
