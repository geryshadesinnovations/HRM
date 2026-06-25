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
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'name'])),
            'designation' => $this->whenLoaded('designation', fn () => $this->designation?->only(['id', 'name'])),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'uuid' => $this->manager->uuid,
                'full_name' => $this->manager->full_name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
