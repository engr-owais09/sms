<?php

namespace App\Modules\Institute\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Institute\Services\TenantDataExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantExportController extends Controller
{
    public function store(Request $request, TenantDataExportService $exportService): RedirectResponse
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            abort(403, 'Active tenant workspace required.');
        }

        $relativePath = $exportService->exportToZip($tenant, $request->user()?->id);
        $signedUrl = URL::temporarySignedRoute(
            'tenant.staff.export.download',
            now()->addMinutes(15),
            ['file' => basename($relativePath)]
        );

        return back()->with('export_download_url', $signedUrl);
    }

    public function download(Request $request): StreamedResponse
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant) {
            abort(403, 'Active tenant workspace required.');
        }

        $file = basename((string) $request->query('file', ''));
        if (!preg_match('/^tenant-export-[A-Za-z0-9_\-]+\.zip$/', $file)) {
            abort(404);
        }

        $relativePath = "exports/{$tenant->id}/{$file}";
        $disk = Storage::disk('local');
        if (!$disk->exists($relativePath)) {
            abort(404);
        }

        return $disk->download($relativePath, $file, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
