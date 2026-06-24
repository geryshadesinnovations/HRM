<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Company\Models\Company;
use App\Domains\Subscription\Enums\SubscriptionStatus;
use App\Domains\Subscription\Models\Plan;
use App\Domains\Subscription\Models\Subscription;
use App\Models\User;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Self-serve company signup: creates the tenant, the first Company Admin user,
 * and a trial subscription on the chosen (or default) plan.
 *
 * See docs/03-SUBSCRIPTION.md (lifecycle) and docs/05-RBAC.md.
 */
final class CompanyRegistrationService
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @return array{company: Company, user: User, subscription: Subscription}
     */
    public function register(
        string $companyName,
        string $adminName,
        string $adminEmail,
        string $password,
        ?string $planCode = null,
    ): array {
        if (User::where('email', $adminEmail)->exists()) {
            throw new ApiException(ErrorCode::ValidationFailed, 'Email already in use.', ['email' => ['taken']], 422);
        }

        $plan = $planCode !== null
            ? Plan::where('code', $planCode)->where('is_active', true)->firstOrFail()
            : Plan::where('is_active', true)->where('is_public', true)->orderBy('base_price')->firstOrFail();

        return DB::transaction(function () use ($companyName, $adminName, $adminEmail, $password, $plan) {
            $company = Company::create([
                'name' => $companyName,
                'slug' => $this->uniqueSlug($companyName),
                'status' => 'active',
            ]);

            // Operate within the new tenant for the rest of the transaction.
            return $this->tenant->forCompany($company->id, function () use ($company, $adminName, $adminEmail, $password, $plan) {
                $user = new User([
                    'company_id' => $company->id,
                    'name' => $adminName,
                    'email' => $adminEmail,
                ]);
                $user->password = $password; // hashed cast
                $user->save();

                $user->assignRole('Company Admin');

                $now = now();
                $trialEnds = $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null;

                $subscription = Subscription::create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => $trialEnds ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
                    'seats' => $plan->included_seats,
                    'trial_ends_at' => $trialEnds,
                    'current_period_start' => $now,
                    'current_period_end' => $this->periodEnd($now, $plan->billing_cycle),
                    'auto_renew' => true,
                    'overrides' => [],
                ]);

                return ['company' => $company, 'user' => $user, 'subscription' => $subscription];
            });
        });
    }

    private function periodEnd(Carbon $start, string $cycle): Carbon
    {
        return $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
