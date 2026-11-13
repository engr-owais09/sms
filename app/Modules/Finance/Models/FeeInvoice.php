<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Institute\Models\Branch;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeInvoice extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'fee_invoices';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'installment_milestone_id',
        'academic_session_id',
        'school_class_id',
        'invoice_number',
        'invoice_type',
        'billing_period',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'late_fee_amount',
        'total_amount',
        'paid_amount',
        'status',
        'psid_1bill',
        'barcode_code128',
        'journal_entry_id',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(InstallmentMilestone::class, 'installment_milestone_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class, 'fee_invoice_id')->orderBy('sort_order', 'asc');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function getBalanceAttribute(): float
    {
        return max(0.00, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->status === 'paid' || $this->balance <= 0.00;
    }
}

