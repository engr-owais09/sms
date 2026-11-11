<?php

namespace App\Core\Tenancy\Contracts;

/**
 * Interface for queue jobs that operate within a specific tenant context.
 */
interface TenantAwareJob
{
    /**
     * Get the tenant ID for this job.
     */
    public function getTenantId(): string;
}
