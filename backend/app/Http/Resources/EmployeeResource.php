<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domains\Employee\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
final class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_code' => $this->employee_code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'date_of_joining' => $this->date_of_joining?->toDateString(),

            // Personal
            'profile_photo_path' => $this->profile_photo_path,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'blood_group' => $this->blood_group,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,

            // Contact
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'current_address' => $this->current_address,
            'permanent_address' => $this->permanent_address,

            // Employment
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'confirmation_date' => $this->confirmation_date?->toDateString(),
            'date_of_exit' => $this->date_of_exit?->toDateString(),

            // Salary / statutory — sensitive identifiers are masked in responses.
            'bank_account_name' => $this->bank_account_name,
            'bank_account_number' => self::mask($this->bank_account_number),
            'bank_ifsc' => $this->bank_ifsc,
            'pan' => self::mask($this->pan),
            'aadhaar' => self::mask($this->aadhaar),
            'uan' => $this->uan,
            'pf_number' => $this->pf_number,
            'esi_number' => $this->esi_number,

            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'name'])),
            'designation' => $this->whenLoaded('designation', fn () => $this->designation?->only(['id', 'name'])),
            'shift' => $this->whenLoaded('shift', fn () => $this->shift?->only(['id', 'name'])),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'uuid' => $this->manager->uuid,
                'full_name' => $this->manager->full_name,
            ] : null),
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Mask all but the last 4 characters of a sensitive identifier. */
    private static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }

        return str_repeat('•', $len - 4).substr($value, -4);
    }
}
