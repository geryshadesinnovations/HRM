<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Contracts;

/**
 * The single gate for all subscription-driven access decisions.
 * See docs/03-SUBSCRIPTION.md.
 */
interface FeatureAccess
{
    public function canAccessModule(string $moduleCode): bool;

    public function canUseFeature(string $featureCode): bool;

    public function featureLimit(string $featureCode): ?int;

    public function canAddEmployee(int $count = 1): bool;

    public function canRunPayroll(): bool;

    /**
     * Assert an ability or throw an ApiException (403).
     *
     * Supported abilities: "module:<code>", "feature:<code>", "payroll", "seat".
     */
    public function assert(string $ability, mixed $context = null): void;
}
