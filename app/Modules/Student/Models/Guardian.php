<?php

namespace App\Modules\Student\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'guardians';

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'cnic',
        'cnic_encrypted',
        'cnic_blind_index',
        'email',
        'phone',
        'occupation',
        'address',
    ];

    protected $casts = [
        'cnic_encrypted' => 'encrypted',
    ];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians', 'guardian_id', 'student_id')
            ->using(StudentGuardian::class)
            ->withPivot([
                'id',
                'tenant_id',
                'relationship_type',
                'is_primary',
                'is_emergency_contact',
                'is_financial_sponsor',
                'can_pickup',
            ])
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public static function computeBlindIndex(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
        $key = config('app.blind_index_key') ?: config('app.key');

        return hash_hmac('sha256', $normalized, $key);
    }

    /**
     * Get decrypted CNIC attribute.
     */
    public function getCnicAttribute(): ?string
    {
        return $this->cnic_encrypted;
    }

    public function setCnicAttribute(?string $value): void
    {
        $this->setAttribute('cnic_encrypted', $value);
        $this->setAttribute('cnic_blind_index', static::computeBlindIndex($value));
    }

    public function scopeWhereCnic(Builder $query, string $cnic): Builder
    {
        return $query->where('cnic_blind_index', static::computeBlindIndex($cnic));
    }
}

