<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Database-driven RBAC: permissions, built-in roles, and assignments.
 * All on the "api" guard. See docs/05-RBAC.md.
 */
class RolePermissionSeeder extends Seeder
{
    /** domain.resource.action */
    private const PERMISSIONS = [
        // Platform (super admin)
        'platform.companies.manage', 'platform.plans.manage', 'platform.modules.manage',
        'platform.subscriptions.manage', 'platform.billing.manage', 'platform.metrics.view',
        'platform.audit.view',
        // Company
        'company.settings.manage', 'company.subscription.manage', 'company.billing.manage',
        'company.roles.manage', 'company.audit.view',
        // Employees
        'employee.profile.view', 'employee.profile.create', 'employee.profile.update',
        'employee.profile.delete', 'employee.import',
        // Attendance
        'attendance.mark', 'attendance.self', 'attendance.view', 'attendance.report.view',
        // Leave
        'leave.request.create', 'leave.request.approve', 'leave.type.manage', 'leave.view',
        // Payroll
        'payroll.structure.manage', 'payroll.run.execute', 'payroll.payslip.view.any',
        'payroll.payslip.view.own', 'payroll.report.view',
    ];

    private const ROLES = [
        'Super Admin' => '*', // all permissions
        'Company Admin' => [
            'company.settings.manage', 'company.subscription.manage', 'company.billing.manage',
            'company.roles.manage', 'company.audit.view',
            'employee.profile.view', 'employee.profile.create', 'employee.profile.update',
            'employee.profile.delete', 'employee.import',
            'attendance.mark', 'attendance.self', 'attendance.view', 'attendance.report.view',
            'leave.request.create', 'leave.request.approve', 'leave.type.manage', 'leave.view',
            'payroll.structure.manage', 'payroll.run.execute', 'payroll.payslip.view.any',
            'payroll.payslip.view.own', 'payroll.report.view',
        ],
        'HR Manager' => [
            'employee.profile.view', 'employee.profile.create', 'employee.profile.update', 'employee.import',
            'attendance.mark', 'attendance.self', 'attendance.view', 'attendance.report.view',
            'leave.request.create', 'leave.request.approve', 'leave.type.manage', 'leave.view',
            'payroll.structure.manage', 'payroll.run.execute', 'payroll.payslip.view.any',
            'payroll.payslip.view.own', 'payroll.report.view',
        ],
        'Manager' => [
            'employee.profile.view',
            'attendance.self', 'attendance.view',
            'leave.request.create', 'leave.request.approve', 'leave.view',
            'payroll.payslip.view.own',
        ],
        'Employee' => [
            'employee.profile.view',
            'attendance.self',
            'leave.request.create', 'leave.view',
            'payroll.payslip.view.own',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'api';

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, $guard);
        }

        foreach (self::ROLES as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, $guard);

            $grant = $perms === '*' ? self::PERMISSIONS : $perms;
            $role->syncPermissions($grant);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
