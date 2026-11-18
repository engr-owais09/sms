<?php

namespace App\Core\Printing;

use App\Core\Printing\Drivers\BrowsershotDriver;
use App\Core\Printing\Support\Barcode128;
use App\Modules\Exam\Models\ExamMark;
use App\Modules\Exam\Models\ExamResult;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class PdfReportService
{
    protected BrowsershotDriver $driver;

    public function __construct(?BrowsershotDriver $driver = null)
    {
        $this->driver = $driver ?? new BrowsershotDriver();
    }

    public function renderFeeVoucher(FeeInvoice $invoice, bool $asPdf = false): Response
    {
        $invoice->load(['tenant', 'branch', 'student.guardians', 'items.feeCategory']);
        $psidRaw = $invoice->psid_1bill ?: str_pad(preg_replace('/\D/', '', $invoice->invoice_number), 18, '0', STR_PAD_LEFT);
        $psidFormatted = trim(chunk_split($psidRaw, 4, ' '));
        $barcodeValue = $invoice->barcode_code128 ?: $psidRaw;

        $data = [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'branch' => $invoice->branch,
            'student' => $invoice->student,
            'psidFormatted' => $psidFormatted,
            'barcodeSvg' => Barcode128::getSvg($barcodeValue, 26, 2, false),
            'urduFontBase64' => $this->getFontBase64(),
        ];

        return $this->deliver(
            'reports.fee-voucher-a4',
            $data,
            "Fee_Voucher_{$invoice->invoice_number}.pdf",
            ['landscape' => true, 'paper_size' => 'a4'],
            $asPdf
        );
    }

    public function renderThermalReceipt(FeePayment $payment, bool $asPdf = false): Response
    {
        $payment->load(['tenant', 'branch', 'student', 'receivedByUser', 'allocations.invoice']);
        $barcodeSvg = Barcode128::getSvg($payment->receipt_number, 30, 1, false);

        $data = [
            'payment' => $payment,
            'tenant' => $payment->tenant,
            'branch' => $payment->branch,
            'student' => $payment->student,
            'barcodeSvg' => $barcodeSvg,
            'urduFontBase64' => $this->getFontBase64(),
        ];

        return $this->deliver(
            'reports.thermal-receipt-80mm',
            $data,
            "Receipt_{$payment->receipt_number}.pdf",
            ['landscape' => false, 'paper_size' => ['width' => 80, 'height' => 210]],
            $asPdf
        );
    }

    public function renderExamReportCard(ExamResult $result, bool $asPdf = false): Response
    {
        $result->load(['student', 'section.schoolClass', 'examTerm.tenant']);
        $marks = ExamMark::with('paper.subject')
            ->where('student_id', $result->student_id)
            ->whereHas('paper', fn ($q) => $q->where('exam_term_id', $result->exam_term_id))
            ->get();

        $data = [
            'tenant' => $result->examTerm->tenant ?? null,
            'result' => $result,
            'marks' => $marks,
            'isRtl' => app()->getLocale() === 'ur',
        ];

        return $this->deliver(
            'reports.bilingual-report-card',
            $data,
            "Report_Card_{$result->student->admission_number}.pdf",
            ['landscape' => false, 'paper_size' => 'a4'],
            $asPdf
        );
    }

    protected function deliver(string $view, array $data, string $filename, array $options, bool $asPdf): Response
    {
        if ($asPdf && $this->driver->isAvailable()) {
            $binary = $this->driver->render($view, $data, $options);
            return new Response($binary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
        }

        return new Response(View::make($view, $data)->render(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    protected function getFontBase64(): ?string
    {
        $path = resource_path('fonts/NotoNastaliqUrdu-Regular.ttf');
        return file_exists($path) ? base64_encode(file_get_contents($path)) : null;
    }
}
