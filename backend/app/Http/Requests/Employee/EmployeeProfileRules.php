<?php

declare(strict_types=1);

namespace App\Http\Requests\Employee;

use App\Domains\Employee\Models\Employee;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for the deep employee profile fields (req #5),
 * reused by both Store and Update employee requests.
 */
final class EmployeeProfileRules
{
    /** @return array<string,mixed> */
    public static function rules(): array
    {
        return [
            // Personal
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:8'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'nationality' => ['nullable', 'string', 'max:60'],
            // Contact
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            // Employment
            'employment_type' => ['nullable', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'work_location' => ['nullable', 'string', 'max:120'],
            'confirmation_date' => ['nullable', 'date'],
            'date_of_exit' => ['nullable', 'date'],
            // Salary / statutory
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
            'aadhaar' => ['nullable', 'string', 'max:20'],
            'uan' => ['nullable', 'string', 'max:40'],
            'pf_number' => ['nullable', 'string', 'max:40'],
            'esi_number' => ['nullable', 'string', 'max:40'],
        ];
    }
}
