<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Student\Models\Student;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCreditWallet extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'student_credit_wallets';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'balance',
        'currency',
        'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StudentCreditTransaction::class, 'student_credit_wallet_id')->orderBy('created_at', 'desc');
    }
}

