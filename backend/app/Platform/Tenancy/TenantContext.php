<?php

declare(strict_types=1);

namespace App\Platform\Tenancy;

/**
 * Holds the active tenant (company) for the current request/job lifecycle.
 *
 * Resolved by ResolveTenant middleware from the authenticated user, or set
 * explicitly inside queued jobs. Super Admin requests run with no tenant.
 *
 * See docs/01-ARCHITECTURE.md (Multi-Tenant Strategy).
 */
final class TenantContext
{
    private ?int $companyId = null;

    private bool $bypassed = false;

    public function setCompanyId(?int $companyId): void
    {
        $this->companyId = $companyId;
    }

    public function companyId(): ?int
    {
        return $this->companyId;
    }

    public function hasTenant(): bool
    {
        return $this->companyId !== null;
    }

    /**
     * Run a callback without tenant scoping (platform/super-admin operations).
     * The global scope checks this flag.
     */
    public function bypass(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Impersonate a tenant for the duration of a callback (audited support actions).
     */
    public function forCompany(int $companyId, callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $companyId;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
