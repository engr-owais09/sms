<?php

namespace App\Modules\Student\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $table = 'student_documents';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'document_type',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'verified_at',
        'verified_by_user_id',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}

