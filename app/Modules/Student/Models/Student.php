<?php

namespace App\Modules\Student\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Modules\Academic\Models\Academy\AcademyBatchEnrollment;
use App\Modules\Academic\Models\School\SchoolStudentEnrollment;
use App\Modules\Institute\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $table = 'students';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'admission_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'b_form',
        'b_form_encrypted',
        'b_form_blind_index',
        'blood_group',
        'status',
        'admission_date',
        'emergency_contact',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
        'b_form_encrypted' => 'encrypted',
        'emergency_contact' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
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

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class, 'student_id');
    }

    public function schoolEnrollments(): HasMany
    {
        return $this->hasMany(SchoolStudentEnrollment::class, 'student_id');
    }

    public function academyEnrollments(): HasMany
    {
        return $this->hasMany(AcademyBatchEnrollment::class, 'student_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Compute a deterministic blind index for exact equality queries without decrypting.
     */
    public static function computeBlindIndex(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Normalize: strip whitespace, hyphens, and convert to uppercase
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
        $key = config('app.blind_index_key') ?: config('app.key');

        return hash_hmac('sha256', $normalized, $key);
    }

    /**
     * Get decrypted B-Form attribute.
     */
    public function getBFormAttribute(): ?string
    {
        return $this->b_form_encrypted;
    }

    /**
     * Set B-Form attribute and auto-generate the blind index.
     */
    public function setBFormAttribute(?string $value): void
    {
        $this->setAttribute('b_form_encrypted', $value);
        $this->setAttribute('b_form_blind_index', static::computeBlindIndex($value));
    }

    /**
     * Query scope to search by B-Form using the blind index.
     */
    public function scopeWhereBForm(Builder $query, string $bForm): Builder
    {
        return $query->where('b_form_blind_index', static::computeBlindIndex($bForm));
    }
}

