<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide analytics for the Super Admin dashboard (req #1). All figures
 * are aggregated across every tenant, so queries run outside tenant scope
 * (DB facade / explicit bypass). Money is returned in minor units (paise).
 *
 * "Access-granting" statuses are trial/active/grace (see SubscriptionStatus).
 */
final class PlatformMetricsService
{
    private const ACTIVE_STATUSES = ['trial', 'active', 'grace'];

    public function __construct(private readonly TenantContext $tenant) {}

    public function dashboard(): array
    {
        return $this->tenant->bypass(fn () => [
            'companies' => $this->companyCounts(),
            'subscriptions' => $this->subscriptionCounts(),
            'revenue' => $this->revenue(),
            'employees' => (int) DB::table('employees')->whereNull('deleted_at')->count(),
            'payroll' => $this->payroll(),
            'attendance' => $this->attendance(),
            'popular_plan' => $this->popularPlan(),
            'module_usage' => $this->moduleUsage(),
            'upcoming_renewals' => $this->upcomingRenewals(),
            'failed_payments' => $this->failedPayments(),
            'growth' => $this->growth(),
            'recent_companies' => $this->recentCompanies(),
            'system_health' => $this->systemHealth(),
        ]);
    }

    private function companyCounts(): array
    {
        $statusByCompany = DB::table('subscriptions')
            ->select('company_id', DB::raw('MAX(id) as latest'))
            ->groupBy('company_id');

        return [
            'total' => (int) DB::table('companies')->whereNull('deleted_at')->count(),
            'active' => (int) DB::table('companies')->whereNull('deleted_at')->where('status', 'active')->count(),
            'suspended' => (int) DB::table('companies')->whereNull('deleted_at')->where('status', 'suspended')->count(),
            'trial' => $this->companiesWithSubscriptionStatus('trial'),
            'expired' => $this->companiesWithSubscriptionStatus('expired'),
        ];
    }

    private function companiesWithSubscriptionStatus(string $status): int
    {
        return (int) DB::table('subscriptions')->where('status', $status)->distinct('company_id')->count('company_id');
    }

    private function subscriptionCounts(): array
    {
        $rows = DB::table('subscriptions')->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        return [
            'active' => (int) DB::table('subscriptions')->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'by_status' => $rows,
        ];
    }

    private function revenue(): array
    {
        // MRR: normalise each access-granting subscription to a monthly amount.
        $subs = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', self::ACTIVE_STATUSES)
            ->get(['subscriptions.seats', 'plans.base_price', 'plans.included_seats', 'plans.per_seat_price', 'plans.billing_cycle']);

        $mrr = 0;
        foreach ($subs as $s) {
            $amount = (int) $s->base_price + max(0, (int) $s->seats - (int) $s->included_seats) * (int) $s->per_seat_price;
            $mrr += $s->billing_cycle === 'yearly' ? intdiv($amount, 12) : $amount;
        }

        $collected = (int) DB::table('payments')->where('status', 'captured')->sum('amount');

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'total_collected' => $collected,
            'currency' => 'INR',
        ];
    }

    private function payroll(): array
    {
        return [
            'runs_completed' => (int) DB::table('payroll_runs')->whereIn('status', ['completed', 'locked'])->count(),
            'total_net' => (int) DB::table('payroll_runs')->whereIn('status', ['completed', 'locked'])->sum('total_net'),
        ];
    }

    private function attendance(): array
    {
        if (! Schema::hasTable('attendance_records')) {
            return ['records' => 0, 'present' => 0];
        }

        return [
            'records' => (int) DB::table('attendance_records')->count(),
            'present' => (int) DB::table('attendance_records')->where('status', 'present')->count(),
        ];
    }

    private function popularPlan(): ?array
    {
        $row = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.status', self::ACTIVE_STATUSES)
            ->select('plans.name', DB::raw('count(*) as c'))
            ->groupBy('plans.name')
            ->orderByDesc('c')
            ->first();

        return $row ? ['name' => $row->name, 'count' => (int) $row->c] : null;
    }

    private function moduleUsage(): array
    {
        return DB::table('subscriptions')
            ->join('plan_module', 'plan_module.plan_id', '=', 'subscriptions.plan_id')
            ->join('modules', 'modules.id', '=', 'plan_module.module_id')
            ->whereIn('subscriptions.status', self::ACTIVE_STATUSES)
            ->select('modules.code', DB::raw('count(*) as c'))
            ->groupBy('modules.code')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($r) => ['module' => $r->code, 'companies' => (int) $r->c])
            ->all();
    }

    private function upcomingRenewals(): array
    {
        $now = now();

        return DB::table('subscriptions')
            ->join('companies', 'companies.id', '=', 'subscriptions.company_id')
            ->whereIn('subscriptions.status', ['active', 'trial'])
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [$now, $now->copy()->addDays(30)])
            ->orderBy('current_period_end')
            ->limit(10)
            ->get(['companies.name', 'subscriptions.current_period_end'])
            ->map(fn ($r) => ['company' => $r->name, 'renews_at' => $r->current_period_end])
            ->all();
    }

    private function failedPayments(): int
    {
        return (int) DB::table('payments')->where('status', 'failed')->count();
    }

    private function growth(): array
    {
        // New companies per month for the last 6 months.
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end = $start->copy()->endOfMonth();
            $series[] = [
                'month' => $start->format('M Y'),
                'companies' => (int) DB::table('companies')
                    ->whereNull('deleted_at')
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];
        }

        return $series;
    }

    private function recentCompanies(): array
    {
        return DB::table('companies')
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['uuid', 'name', 'status', 'created_at'])
            ->map(fn ($r) => ['uuid' => $r->uuid, 'name' => $r->name, 'status' => $r->status, 'created_at' => $r->created_at])
            ->all();
    }

    private function systemHealth(): array
    {
        $db = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = false;
        }

        $cache = true;
        try {
            cache()->put('health_probe', '1', 5);
            $cache = cache()->get('health_probe') === '1';
        } catch (\Throwable) {
            $cache = false;
        }

        return ['database' => $db, 'cache' => $cache];
    }
}
