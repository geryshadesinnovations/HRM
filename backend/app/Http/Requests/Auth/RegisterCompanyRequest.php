<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:180'],
            'admin_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan_code' => ['nullable', 'string', 'exists:plans,code'],
            // Optional company profile (req #3, step 1)
            'phone' => ['nullable', 'string', 'max:30'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'industry' => ['nullable', 'string', 'max:80'],
            'employees_estimate' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
