<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Subscription\Models\Feature;
use App\Domains\Subscription\Models\Module;
use App\Domains\Subscription\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Default public plans with module + feature mappings. Prices in paise (INR).
 * Plan-to-module mapping is data-driven. See docs/03-SUBSCRIPTION.md.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter', 'name' => 'Starter', 'billing_cycle' => 'monthly',
                'base_price' => 99900, 'included_seats' => 25, 'per_seat_price' => 2000,
                'trial_days' => 14,
                'modules' => ['attendance', 'leave'],
                'features' => ['attendance.self' => 'true', 'leave.carry_forward' => 'true'],
            ],
            [
                'code' => 'growth', 'name' => 'Growth', 'billing_cycle' => 'monthly',
                'base_price' => 299900, 'included_seats' => 100, 'per_seat_price' => 3000,
                'trial_days' => 14,
                'modules' => ['attendance', 'leave', 'payroll'],
                'features' => [
                    'attendance.self' => 'true', 'attendance.gps' => 'true',
                    'leave.carry_forward' => 'true', 'leave.encashment' => 'true',
                    'payroll.bulk' => 'true', 'payroll.statutory' => 'true',
                ],
            ],
            [
                'code' => 'enterprise', 'name' => 'Enterprise', 'billing_cycle' => 'monthly',
                'base_price' => 999900, 'included_seats' => 500, 'per_seat_price' => 2500,
                'trial_days' => 30,
                'modules' => ['attendance', 'leave', 'payroll', 'recruitment', 'assets', 'performance', 'expenses'],
                'features' => [
                    'attendance.self' => 'true', 'attendance.gps' => 'true', 'attendance.biometric' => 'true',
                    'leave.carry_forward' => 'true', 'leave.encashment' => 'true',
                    'payroll.bulk' => 'true', 'payroll.statutory' => 'true',
                    'employees.max' => 'unlimited',
                ],
            ],
        ];

        foreach ($plans as $data) {
            $modules = $data['modules'];
            $features = $data['features'];
            unset($data['modules'], $data['features']);

            $plan = Plan::updateOrCreate(['code' => $data['code']], $data);

            $plan->modules()->sync(Module::whereIn('code', $modules)->pluck('id'));

            $sync = [];
            foreach ($features as $code => $value) {
                $feature = Feature::where('code', $code)->first();
                if ($feature !== null) {
                    $sync[$feature->id] = ['value' => $value];
                }
            }
            $plan->features()->sync($sync);
        }
    }
}
