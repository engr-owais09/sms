<?php

namespace App\Modules\Institute\Services;

use App\Core\Audit\Models\TenantAuditLog;
use App\Core\Audit\Services\TenantAuditService;
use App\Modules\Finance\Models\ExpenseVoucher;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Models\StudentDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class TenantDataExportService
{
    public function exportToZip(Tenant $tenant, ?int $actorUserId = null): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is required for tenant data export.');
        }

        $disk = Storage::disk('local');
        $relativeDir = "exports/{$tenant->id}";
        $disk->makeDirectory($relativeDir);

        $filename = 'tenant-export-'.now()->format('Ymd_His').'-'.substr((string) \Illuminate\Support\Str::uuid(), 0, 8).'.zip';
        $relativePath = "{$relativeDir}/{$filename}";
        $fullPath = $disk->path($relativePath);

        $zip = new ZipArchive();
        if ($zip->open($fullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create export archive at {$relativePath}.");
        }

        $datasets = [
            'tenant.json' => $tenant->toArray(),
            'branches.json' => Branch::where('tenant_id', $tenant->id)->get()->toArray(),
            'students.json' => Student::where('tenant_id', $tenant->id)->get()->toArray(),
            'guardians.json' => Guardian::where('tenant_id', $tenant->id)->get()->toArray(),
            'fee_invoices.json' => FeeInvoice::where('tenant_id', $tenant->id)->get()->toArray(),
            'fee_payments.json' => FeePayment::where('tenant_id', $tenant->id)->get()->toArray(),
            'expense_vouchers.json' => ExpenseVoucher::where('tenant_id', $tenant->id)->get()->toArray(),
            'documents_manifest.json' => StudentDocument::where('tenant_id', $tenant->id)
                ->get(['id', 'student_id', 'document_type', 'original_filename', 'file_path', 'mime_type', 'file_size_bytes', 'verified_at'])
                ->toArray(),
            'audit_logs.json' => TenantAuditLog::where('tenant_id', $tenant->id)->get()->toArray(),
        ];

        foreach ($datasets as $name => $payload) {
            $zip->addFromString($name, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $zip->close();

        TenantAuditService::record(
            'tenant.exported',
            Tenant::class,
            $tenant->id,
            ['archive' => $filename],
            $tenant->id,
            $actorUserId
        );

        return $relativePath;
    }
}
