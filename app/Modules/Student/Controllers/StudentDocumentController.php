<?php

namespace App\Modules\Student\Controllers;

use App\Core\Audit\Services\TenantAuditService;
use App\Core\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Modules\Student\Models\StudentDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDocumentController extends Controller
{
    public function download(Request $request, StudentDocument $document): StreamedResponse
    {
        $tenant = app(TenantContext::class)->getTenant();
        if (!$tenant || $document->tenant_id !== $tenant->id) {
            abort(403, 'Document does not belong to this workspace.');
        }

        $disk = Storage::disk(config('filesystems.documents_disk', 'local'));
        if (!$disk->exists($document->file_path)) {
            abort(404, 'Stored document not found.');
        }

        TenantAuditService::record(
            'document.downloaded',
            StudentDocument::class,
            $document->id,
            ['student_id' => $document->student_id, 'filename' => $document->original_filename],
            $tenant->id,
            $request->user()?->id
        );

        return $disk->download($document->file_path, $document->original_filename, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
