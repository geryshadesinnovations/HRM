<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Enums;

/**
 * Subscription lifecycle states. See docs/03-SUBSCRIPTION.md.
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Whether module/feature access is granted in this state.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace], true);
    }

    public function isBlocked(): bool
    {
        return ! $this->grantsAccess();
    }
}
