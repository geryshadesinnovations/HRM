<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Subscription\Models\Feature;
use App\Domains\Subscription\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Canonical modules + features. Idempotent (safe to re-run).
 * See docs/06-MODULES.md.
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['code' => 'attendance', 'name' => 'Attendance Management', 'sort_order' => 10, 'features' => [
                ['code' => 'attendance.self', 'name' => 'Employee Self Attendance', 'type' => 'boolean'],
                ['code' => 'attendance.gps', 'name' => 'GPS Attendance', 'type' => 'boolean'],
                ['code' => 'attendance.biometric', 'name' => 'Biometric Integration', 'type' => 'boolean'],
            ]],
            ['code' => 'leave', 'name' => 'Leave Management', 'sort_order' => 20, 'features' => [
                ['code' => 'leave.encashment', 'name' => 'Leave Encashment', 'type' => 'boolean'],
                ['code' => 'leave.carry_forward', 'name' => 'Carry Forward', 'type' => 'boolean'],
            ]],
            ['code' => 'payroll', 'name' => 'Payroll Management', 'sort_order' => 30, 'features' => [
                ['code' => 'payroll.bulk', 'name' => 'Bulk Payroll Run', 'type' => 'boolean'],
                ['code' => 'payroll.statutory', 'name' => 'PF/ESI/Tax', 'type' => 'boolean'],
            ]],
            ['code' => 'recruitment', 'name' => 'Recruitment', 'sort_order' => 40, 'features' => []],
            ['code' => 'assets', 'name' => 'Asset Management', 'sort_order' => 50, 'features' => []],
            ['code' => 'performance', 'name' => 'Performance', 'sort_order' => 60, 'features' => []],
            ['code' => 'expenses', 'name' => 'Expenses', 'sort_order' => 70, 'features' => []],
        ];

        foreach ($modules as $data) {
            $features = $data['features'];
            unset($data['features']);

            $module = Module::updateOrCreate(['code' => $data['code']], $data);

            foreach ($features as $feature) {
                Feature::updateOrCreate(
                    ['code' => $feature['code']],
                    array_merge($feature, ['module_id' => $module->id]),
                );
            }
        }

        // Cross-cutting (non-module) limit feature used for seat math.
        Feature::updateOrCreate(
            ['code' => 'employees.max'],
            ['name' => 'Maximum Employees', 'type' => 'limit', 'module_id' => null],
        );
    }
}
