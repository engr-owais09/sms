<?php

namespace App\Modules\Exam\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScale extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'grading_scales';

    protected $fillable = [
        'tenant_id',
        'name',
        'scale_type',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function bands(): HasMany
    {
        return $this->hasMany(GradingScaleBand::class, 'grading_scale_id')->orderByDesc('min_percentage');
    }
}
