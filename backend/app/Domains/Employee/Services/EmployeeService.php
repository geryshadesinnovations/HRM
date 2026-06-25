<?php

declare(strict_types=1);

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Subscription\Contracts\FeatureAccess;
use Illuminate\Support\Facades\DB;

/**
 * Employee lifecycle operations. Creation is gated by the subscription seat
 * limit via the FeatureAccess service (throws 403 SEAT_LIMIT_REACHED).
 *
 * See docs/06-MODULES.md (Employee Module) and docs/03-SUBSCRIPTION.md.
 */
final class EmployeeService
{
    public function __construct(private readonly FeatureAccess $access) {}

    /**
     * @param  array<string,mixed>  $data
     */
    public function create(array $data): Employee
    {
        // Only active employees consume a seat; gate before persisting.
        if (($data['status'] ?? 'active') === 'active') {
            $this->access->assert('seat', 1);
        }

        return DB::transaction(fn () => Employee::create($data));
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        // Re-activating a terminated/on-leave employee re-consumes a seat.
        $reactivating = ($data['status'] ?? null) === 'active' && $employee->status !== 'active';

        if ($reactivating) {
            $this->access->assert('seat', 1);
        }

        return DB::transaction(function () use ($employee, $data) {
            $employee->fill($data)->save();

            return $employee->refresh();
        });
    }

    public function delete(Employee $employee): void
    {
        $employee->delete(); // soft delete; frees a seat
    }
}
