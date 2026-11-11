<?php

namespace App\Core\Tenancy;

use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;

class TenantContext
{
    protected ?Tenant $tenant = null;
    protected ?Branch $branch = null;

    /**
     * Set the current active tenant.
     */
    public function setTenant(?Tenant $tenant): self
    {
        $this->tenant = $tenant;

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($tenant?->id);
        }

        return $this;
    }

    /**
     * Get the current active tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the current active tenant UUID string.
     */
    public function getTenantId(): ?string
    {
        return $this->tenant?->id;
    }

    /**
     * Set the current active branch.
     */
    public function setBranch(?Branch $branch): self
    {
        $this->branch = $branch;
        return $this;
    }

    /**
     * Get the current active branch.
     */
    public function getBranch(): ?Branch
    {
        return $this->branch;
    }

    /**
     * Get the current active branch UUID string.
     */
    public function getBranchId(): ?string
    {
        return $this->branch?->id;
    }

    /**
     * Determine if a tenant context is actively resolved.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Clear all active context state.
     */
    public function clear(): void
    {
        $this->tenant = null;
        $this->branch = null;

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
    }
}
