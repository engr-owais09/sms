<?php

namespace App\Modules\Finance\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'tenant_id',
        'account_code',
        'account_name',
        'account_type',
        'subledger_type',
        'is_system_controlled',
        'is_active',
        'parent_account_id',
        'currency',
        'description',
    ];

    protected $casts = [
        'is_system_controlled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function isAsset(): bool
    {
        return $this->account_type === 'asset';
    }

    public function isLiability(): bool
    {
        return $this->account_type === 'liability';
    }

    public function isEquity(): bool
    {
        return $this->account_type === 'equity';
    }

    public function isRevenue(): bool
    {
        return $this->account_type === 'revenue';
    }

    public function isExpense(): bool
    {
        return $this->account_type === 'expense';
    }
}

