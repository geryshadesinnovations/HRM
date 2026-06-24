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
        ];
    }
}
