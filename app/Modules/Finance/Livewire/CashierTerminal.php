<?php

namespace App\Modules\Finance\Livewire;

use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Finance\Models\CashierSession;
use App\Modules\Finance\Models\FeeCategory;
use App\Modules\Finance\Models\FeeInvoice;
use App\Modules\Finance\Models\FeePayment;
use App\Modules\Finance\Services\PaymentProcessingService;
use App\Modules\Student\Models\Student;
use Livewire\Component;

class CashierTerminal extends Component
{
    public string $searchQuery = '';
    public ?string $selectedStudentId = null;
    public ?Student $selectedStudent = null;
    public array $invoices = [];
    public ?string $selectedInvoiceId = null;

    public string $paymentMethod = 'cash';
    public float $amountTendered = 0.00;
    public string $instrumentReference = '';
    public string $notes = '';

    public bool $isAdvance = false;
    public ?string $advanceCategoryId = null;
    public string $targetAdvancePeriod = '';

    public ?string $activeSessionCode = null;
    public ?string $activeSessionId = null;
    public ?string $successMessage = null;
    public ?string $errorMessage = null;
    public ?FeePayment $lastPayment = null;

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->getTenant();
        $user = auth()->user();

        if ($tenant && $user) {
            $session = CashierSession::where('tenant_id', $tenant->id)
                ->where('cashier_user_id', $user->id)
                ->where('status', 'open')
                ->latest()
                ->first();

            if ($session) {
                $this->activeSessionCode = $session->session_code;
                $this->activeSessionId = $session->id;
            }
        }

        $defaultCat = FeeCategory::where('code', 'TUITION')->first();
        if ($defaultCat) {
            $this->advanceCategoryId = $defaultCat->id;
        }
        $this->targetAdvancePeriod = now()->addMonth()->format('Y-m');
    }

    public function updatedSearchQuery(): void
    {
        $this->resetFeedback();
        $this->invoices = [];
        $this->selectedStudent = null;
        $this->selectedStudentId = null;
        $this->selectedInvoiceId = null;

        if (trim($this->searchQuery) === '') {
            return;
        }

        $term = trim($this->searchQuery);

        // Search by invoice barcode / PSID / number first (grouped to preserve TenantScope)
        $invoice = FeeInvoice::with('student')
            ->where(function ($q) use ($term) {
                $q->where('invoice_number', $term)
                    ->orWhere('barcode_code128', $term)
                    ->orWhere('psid_1bill', $term);
            })
            ->first();

        if ($invoice && $invoice->student) {
            $this->selectStudent($invoice->student->id);
            $this->selectInvoice($invoice->id);
            return;
        }

        // Search by student admission number or name (grouped to preserve TenantScope)
        $student = Student::where(function ($q) use ($term) {
            $q->where('admission_number', $term)
                ->orWhere('first_name', 'ILIKE', '%' . $term . '%')
                ->orWhere('last_name', 'ILIKE', '%' . $term . '%');
        })->first();

        if ($student) {
            $this->selectStudent($student->id);
        }
    }

    public function selectStudent(string $studentId): void
    {
        $this->resetFeedback();
        $this->selectedStudentId = $studentId;
        $this->selectedStudent = Student::with(['branch', 'schoolEnrollments.schoolSection.schoolClass', 'academyEnrollments.batch.course'])
            ->find($studentId);

        if (!$this->selectedStudent) {
            $this->errorMessage = 'Student record not found.';
            return;
        }

        $this->loadPendingInvoices();
    }

    public function loadPendingInvoices(): void
    {
        if (!$this->selectedStudent) {
            return;
        }

        $queryInvoices = FeeInvoice::where('student_id', $this->selectedStudent->id)
            ->whereIn('status', ['unpaid', 'partially_paid', 'issued'])
            ->orderBy('due_date', 'asc')
            ->get();

        $this->invoices = [];
        $totalOutstanding = 0.00;

        foreach ($queryInvoices as $inv) {
            $remaining = round((float) $inv->total_amount - (float) $inv->paid_amount, 2);
            $isOverdue = now()->gt($inv->due_date);
            $lateFine = $isOverdue ? round((float) $inv->late_fee_amount, 2) : 0.00;

            $this->invoices[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'billing_period' => $inv->billing_period,
                'issue_date' => $inv->issue_date->format('Y-m-d'),
                'due_date' => $inv->due_date->format('Y-m-d'),
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'remaining_balance' => $remaining,
                'is_overdue' => $isOverdue,
                'late_fine' => $lateFine,
            ];

            $totalOutstanding += $remaining;
        }

        if (!empty($this->invoices)) {
            $this->selectInvoice($this->invoices[0]['id']);
        } else {
            $this->amountTendered = 0.00;
        }
    }

    public function selectInvoice(string $invoiceId): void
    {
        $this->selectedInvoiceId = $invoiceId;
        foreach ($this->invoices as $inv) {
            if ($inv['id'] === $invoiceId) {
                $this->amountTendered = $inv['remaining_balance'];
                break;
            }
        }
    }

    public function processPayment(): void
    {
        $this->resetFeedback();

        if (!$this->selectedStudent) {
            $this->errorMessage = 'Please search and select a student first.';
            return;
        }

        if ($this->amountTendered <= 0) {
            $this->errorMessage = 'Payment amount must be greater than zero.';
            return;
        }

        $user = auth()->user();
        if (!$user) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }
        $paymentService = app(PaymentProcessingService::class);

        try {
            $options = [
                'payment_method' => $this->paymentMethod,
                'received_by_user_id' => $user->id,
                'cashier_session_id' => $this->activeSessionId,
                'instrument_reference' => $this->instrumentReference ?: null,
                'notes' => $this->notes ?: 'Cashier Counter Terminal Payment',
            ];

            if ($this->selectedInvoiceId) {
                $options['target_invoice_ids'] = [$this->selectedInvoiceId];
            }

            if ($this->isAdvance) {
                $options['target_advance_period'] = $this->targetAdvancePeriod;
                $options['target_advance_category_id'] = $this->advanceCategoryId;
            }

            $payment = $paymentService->processPayment($this->selectedStudent, $this->amountTendered, $options);

            $this->lastPayment = $payment;
            $this->successMessage = "Payment of PKR " . number_format($this->amountTendered, 2) . " processed successfully! Receipt: #{$payment->receipt_number}";

            // Reset inputs
            $this->amountTendered = 0.00;
            $this->instrumentReference = '';
            $this->notes = '';
            $this->loadPendingInvoices();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Payment failed: ' . $e->getMessage();
        }
    }

    protected function resetFeedback(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        $tenant = app(TenantContext::class)->getTenant();
        $categories = FeeCategory::where('is_active', true)->get();

        return view('livewire.finance.cashier-terminal', [
            'tenant' => $tenant,
            'categories' => $categories,
            'isRtl' => app()->getLocale() === 'ur',
        ]);
    }
}
