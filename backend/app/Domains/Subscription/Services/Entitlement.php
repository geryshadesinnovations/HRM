<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Services;

/**
 * Immutable, resolved entitlement snapshot for a company at a point in time.
 *
 *  - $modules:  set of granted module codes
 *  - $features: feature code => value ('true'|'false'|int-as-string|'unlimited')
 *  - $accessGranted: subscription status gate (active/trial/grace)
 *
 * See docs/03-SUBSCRIPTION.md.
 */
final class Entitlement
{
    /**
     * @param  list<string>  $modules
     * @param  array<string,string>  $features
     */
    public function __construct(
        public readonly array $modules,
        public readonly array $features,
        public readonly bool $accessGranted,
        public readonly int $includedSeats,
        public readonly int $purchasedSeats,
        public readonly string $status,
    ) {}

    public function hasModule(string $code): bool
    {
        return $this->accessGranted && in_array($code, $this->modules, true);
    }

    public function featureValue(string $code): ?string
    {
        return $this->features[$code] ?? null;
    }

    public function booleanFeature(string $code): bool
    {
        return $this->accessGranted
            && in_array($this->featureValue($code), ['true', '1', 'unlimited'], true);
    }

    /** Null means unlimited / not configured. */
    public function featureLimit(string $code): ?int
    {
        $value = $this->featureValue($code);

        if ($value === null || $value === 'unlimited') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    public function seatLimit(): ?int
    {
        // explicit employees.max feature overrides seat math when present
        $featureMax = $this->featureLimit('employees.max');
        if ($featureMax !== null) {
            return $featureMax;
        }

        return $this->includedSeats + $this->purchasedSeats;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'access_granted' => $this->accessGranted,
            'modules' => $this->modules,
            'features' => $this->features,
            'seats' => [
                'included' => $this->includedSeats,
                'purchased' => $this->purchasedSeats,
                'limit' => $this->seatLimit(),
            ],
        ];
    }
}
