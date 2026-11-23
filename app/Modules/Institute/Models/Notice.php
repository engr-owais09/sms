<?php

namespace App\Modules\Institute\Models;

use App\Core\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'notices';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'author_user_id',
        'title',
        'title_ur',
        'body',
        'body_ur',
        'audience',
        'school_class_id',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];
}
