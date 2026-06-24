<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Models\User;
use App\Platform\Auth\JwtService;
use App\Platform\Auth\Models\RefreshToken;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Authentication operations: login, refresh-token rotation, logout.
 * Company signup lives in Identity\Services\CompanyRegistrationService.
 *
 * See docs/08-SECURITY.md.
 */
final class AuthService
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * @return array{user: User, tokens: array}
     */
    public function login(string $email, string $password, Request $request): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new ApiException(ErrorCode::Unauthenticated, 'Invalid credentials.', [], 401);
        }

        return ['user' => $user, 'tokens' => $this->issueTokens($user, $request)];
    }

    /**
     * Rotate a refresh token: validate, revoke the old one, issue a new pair.
     * Detects reuse of an already-revoked token and revokes the whole chain.
     */
    public function refresh(string $refreshToken, Request $request): array
    {
        try {
            $payload = $this->jwt->decode($refreshToken);
        } catch (\Throwable) {
            throw new ApiException(ErrorCode::Unauthenticated, 'Invalid refresh token.', [], 401);
        }

        if (($payload->typ ?? null) !== 'refresh') {
            throw new ApiException(ErrorCode::Unauthenticated, 'Invalid refresh token.', [], 401);
        }

        $record = RefreshToken::where('jti', $payload->jti)->first();

        if ($record === null || ! $record->isActive()) {
            // Possible token reuse — revoke all of the user's tokens defensively.
            if ($record !== null) {
                RefreshToken::where('user_id', $record->user_id)->update(['revoked_at' => now()]);
            }
            throw new ApiException(ErrorCode::Unauthenticated, 'Refresh token is no longer valid.', [], 401);
        }

        $user = User::findOrFail($record->user_id);

        return DB::transaction(function () use ($user, $record, $request) {
            $record->update(['revoked_at' => now()]);

            return ['user' => $user, 'tokens' => $this->issueTokens($user, $request)];
        });
    }

    public function logout(User $user): void
    {
        RefreshToken::where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issueTokens(User $user, Request $request): array
    {
        $access = $this->jwt->issueAccessToken($user);
        $refresh = $this->jwt->issueRefreshToken($user);

        RefreshToken::create([
            'user_id' => $user->getKey(),
            'jti' => $refresh['jti'],
            'token_hash' => hash('sha256', $refresh['token']),
            'expires_at' => now()->setTimestamp($refresh['expires_at']),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'ip_address' => $request->ip(),
        ]);

        return [
            'access_token' => $access,
            'refresh_token' => $refresh['token'],
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->accessTtl(),
        ];
    }
}
