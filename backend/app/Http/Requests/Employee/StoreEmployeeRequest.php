<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Domains\Employee\Models\Employee;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route guarded by auth + permission middleware
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->companyId();

        return [
            'employee_code' => [
                'required', 'string', 'max:40',
                Rule::unique('employees', 'employee_code')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('company_id', $companyId)],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->where('company_id', $companyId)],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')->where('company_id', $companyId)],
            'date_of_joining' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(Employee::STATUSES)],
            ...EmployeeProfileRules::rules(),
        ];
    }
}
