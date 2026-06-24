<?php

declare(strict_types=1);

namespace App\Platform\Auth\Contracts;

interface JwtSubject
{
    /** Unique, stable identifier embedded as the token subject. */
    public function getJwtIdentifier(): string;

    /** Extra claims embedded in the access token. */
    public function getJwtCustomClaims(): array;
}
