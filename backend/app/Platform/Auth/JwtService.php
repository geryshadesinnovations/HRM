<?php

declare(strict_types=1);

namespace App\Platform\Auth;

use App\Platform\Auth\Contracts\JwtSubject;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use stdClass;

/**
 * Issues and verifies JWT access + refresh tokens (HS256 by default).
 *
 * Access tokens carry user claims; refresh tokens are opaque-ish JWTs with a
 * jti used for rotation/revocation (revocation list persisted by AuthService).
 *
 * See docs/08-SECURITY.md (Authentication).
 */
final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algo,
        private readonly int $accessTtl,
        private readonly int $refreshTtl,
        private readonly string $issuer,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('jwt.secret'),
            (string) config('jwt.algo', 'HS256'),
            (int) config('jwt.access_ttl', 900),
            (int) config('jwt.refresh_ttl', 1209600),
            (string) config('jwt.issuer', 'hrms'),
        );
    }

    public function issueAccessToken(JwtSubject $subject): string
    {
        $now = time();

        $payload = array_merge($subject->getJwtCustomClaims(), [
            'iss' => $this->issuer,
            'sub' => $subject->getJwtIdentifier(),
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'typ' => 'access',
            'jti' => (string) Str::uuid(),
        ]);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * @return array{token: string, jti: string, expires_at: int}
     */
    public function issueRefreshToken(JwtSubject $subject): array
    {
        $now = time();
        $jti = (string) Str::uuid();
        $exp = $now + $this->refreshTtl;

        $token = JWT::encode([
            'iss' => $this->issuer,
            'sub' => $subject->getJwtIdentifier(),
            'iat' => $now,
            'exp' => $exp,
            'typ' => 'refresh',
            'jti' => $jti,
        ], $this->secret, $this->algo);

        return ['token' => $token, 'jti' => $jti, 'expires_at' => $exp];
    }

    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret, $this->algo));
    }

    public function accessTtl(): int
    {
        return $this->accessTtl;
    }
}
