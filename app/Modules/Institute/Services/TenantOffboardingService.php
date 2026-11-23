<?php

namespace App\Modules\Institute\Services;

use App\Modules\Institute\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantOffboardingService
{
    public function __construct(
        protected TenantDataExportService $exportService
    ) {}

    /**
     * Suspend -> Export archive -> Soft-delete tenant.
     */
    public function offboard(Tenant $tenant, ?int $actorUserId = null): string
    {
        $tenant->update(['status' => 'suspended']);
        $archivePath = $this->exportService->exportToZip($tenant, $actorUserId);
        $tenant->delete();

        return $archivePath;
    }

    public function setLegalHold(Tenant $tenant, bool $hold): void
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['legal_hold'] = $hold;
        $tenant->update(['settings' => $settings]);
    }

    /**
     * Hard-delete soft-deleted tenants past retentionDays, skipping any under legal_hold.
     */
    public function purgeExpired(int $retentionDays = 30): int
    {
        $cutoff = now()->subDays(max(1, $retentionDays));
        $purged = 0;

        Tenant::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get()
            ->each(function (Tenant $tenant) use (&$purged): void {
                $legalHold = (bool) ($tenant->settings['legal_hold'] ?? false);
                if ($legalHold) {
                    return;
                }

                DB::transaction(function () use ($tenant): void {
                    $disk = Storage::disk(config('filesystems.documents_disk', 'local'));
                    $disk->deleteDirectory("tenants/{$tenant->id}");
                    $tenant->forceDelete();
                });

                $purged++;
            });

        return $purged;
    }
}
