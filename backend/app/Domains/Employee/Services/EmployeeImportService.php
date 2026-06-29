<?php

declare(strict_types=1);

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Models\Department;
use App\Domains\Employee\Models\Designation;
use App\Domains\Employee\Models\Employee;
use App\Domains\Subscription\Contracts\FeatureAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bulk employee import from CSV (req: bulk employee import).
 *
 * Each row is validated independently; valid rows are inserted, invalid rows
 * are reported with a reason so the importer can fix and re-upload. Seat limits
 * are enforced up-front for the whole batch via FeatureAccess. Departments and
 * designations referenced by name are created on the fly within the tenant.
 */
final class EmployeeImportService
{
    /** Recognised CSV headers (case-insensitive, order-independent). */
    public const COLUMNS = [
        'employee_code', 'first_name', 'last_name', 'email', 'phone',
        'department', 'designation', 'date_of_joining', 'employment_type',
        'gender', 'work_location',
    ];

    public function __construct(private readonly FeatureAccess $access) {}

    /**
     * @return array{summary:array<string,int>, rows:array<int,array<string,mixed>>}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->parse($file);

        // Seat gate for the whole batch (active employees consume seats).
        if ($rows !== []) {
            $this->access->assert('seat', count($rows));
        }

        $created = 0;
        $failed = 0;
        $results = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2; // +1 for header, +1 for 1-based

            $error = $this->validateRow($row);
            if ($error !== null) {
                $failed++;
                $results[] = ['line' => $line, 'status' => 'error', 'message' => $error, 'employee_code' => $row['employee_code'] ?? null];

                continue;
            }

            try {
                $employee = DB::transaction(fn () => $this->createFromRow($row));
                $created++;
                $results[] = ['line' => $line, 'status' => 'created', 'uuid' => $employee->uuid, 'employee_code' => $employee->employee_code];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = ['line' => $line, 'status' => 'error', 'message' => $e->getMessage(), 'employee_code' => $row['employee_code'] ?? null];
            }
        }

        return [
            'summary' => ['total' => count($rows), 'created' => $created, 'failed' => $failed],
            'rows' => $results,
        ];
    }

    /**
     * Parse a CSV upload into an array of associative rows keyed by header.
     *
     * @return array<int,array<string,string>>
     */
    private function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return [];
        }

        $headers = null;
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            // Skip fully empty lines.
            if ($data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);

                continue;
            }

            $row = [];
            foreach ($headers as $idx => $key) {
                $row[$key] = isset($data[$idx]) ? trim((string) $data[$idx]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /** @param array<string,string> $row */
    private function validateRow(array $row): ?string
    {
        if (($row['employee_code'] ?? '') === '') {
            return 'employee_code is required.';
        }
        if (($row['first_name'] ?? '') === '') {
            return 'first_name is required.';
        }
        if (($row['email'] ?? '') !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            return 'email is not a valid address.';
        }
        if (Employee::where('employee_code', $row['employee_code'])->exists()) {
            return "employee_code '{$row['employee_code']}' already exists.";
        }
        $type = $row['employment_type'] ?? '';
        if ($type !== '' && ! in_array($type, Employee::EMPLOYMENT_TYPES, true)) {
            return 'employment_type must be one of: '.implode(', ', Employee::EMPLOYMENT_TYPES).'.';
        }

        return null;
    }

    /** @param array<string,string> $row */
    private function createFromRow(array $row): Employee
    {
        $departmentId = null;
        if (($row['department'] ?? '') !== '') {
            $departmentId = Department::firstOrCreate(['name' => $row['department']])->id;
        }

        $designationId = null;
        if (($row['designation'] ?? '') !== '') {
            $designationId = Designation::firstOrCreate(
                ['name' => $row['designation']],
                ['department_id' => $departmentId],
            )->id;
        }

        $doj = ($row['date_of_joining'] ?? '') !== ''
            ? Carbon::parse($row['date_of_joining'])->toDateString()
            : null;

        return Employee::create([
            'employee_code' => $row['employee_code'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'] ?: null,
            'email' => $row['email'] ?: null,
            'phone' => $row['phone'] ?: null,
            'department_id' => $departmentId,
            'designation_id' => $designationId,
            'date_of_joining' => $doj,
            'employment_type' => ($row['employment_type'] ?? '') ?: null,
            'gender' => ($row['gender'] ?? '') ?: null,
            'work_location' => ($row['work_location'] ?? '') ?: null,
            'status' => 'active',
        ]);
    }
}
