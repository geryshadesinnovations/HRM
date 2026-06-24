<?php

declare(strict_types=1);

namespace App\Platform\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Throwable;

/**
 * Stateless guard that authenticates requests from a Bearer access token.
 *
 * Registered as the "api" guard via Auth::extend in TenancyServiceProvider.
 */
final class JwtGuard implements Guard
{
    private ?Authenticatable $user = null;

    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserProvider $provider,
        private readonly Request $request,
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if ($token === null) {
            return null;
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (Throwable) {
            return null;
        }

        if (($payload->typ ?? null) !== 'access') {
            return null;
        }

        return $this->user = $this->provider->retrieveById($payload->sub);
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user !== null
            && $this->provider->validateCredentials($user, $credentials);
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
