<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Subscription\Contracts\FeatureAccess;
use App\Domains\Subscription\Services\FeatureAccessService;
use App\Domains\Subscription\Services\SubscriptionEngine;
use App\Platform\Auth\JwtGuard;
use App\Platform\Auth\JwtService;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the platform spine: tenancy, JWT guard, subscription engine, and the
 * Feature Access Service.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request lifecycle.
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);

        $this->app->singleton(JwtService::class, fn () => JwtService::fromConfig());

        $this->app->scoped(SubscriptionEngine::class);

        $this->app->scoped(FeatureAccess::class, FeatureAccessService::class);
    }

    public function boot(): void
    {
        Auth::extend('jwt', function ($app, string $name, array $config) {
            return new JwtGuard(
                $app->make(JwtService::class),
                Auth::createUserProvider($config['provider']),
                $app['request'],
            );
        });
    }
}
