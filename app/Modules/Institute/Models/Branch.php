<?php

namespace App\Modules\Institute\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'branches';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'city',
        'address',
        'phone',
        'is_main',
        'status',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function isMainBranch(): bool
    {
        return $this->is_main;
    }
}
