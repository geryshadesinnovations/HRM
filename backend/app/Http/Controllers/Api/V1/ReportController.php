<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Payroll\Models\PayrollRun;
use App\Domains\Reporting\Services\ReportService;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use App\Platform\Support\Csv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reporting endpoints. Each returns JSON by default, or a CSV download when
 * ?format=csv. Gated by module + the relevant report/view permission.
 *
 * See docs/09-REPORTING-NOTIFICATIONS.md.
 */
final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function attendanceSummary(Request $request): Response
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->endOfMonth()->toDateString();

        $rows = $this->reports->attendanceSummary($from, $to);

        return $this->respond($request, $rows, "attendance-summary-{$from}_to_{$to}.csv", [
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function leaveBalances(Request $request): Response
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $rows = $this->reports->leaveBalances($year);

        return $this->respond($request, $rows, "leave-balances-{$year}.csv", ['year' => $year]);
    }

    public function payrollRegister(Request $request, PayrollRun $run): Response
    {
        $report = $this->reports->payrollRegister($run);

        if ($request->query('format') === 'csv') {
            return Csv::download("payroll-register-{$run->period_year}-{$run->period_month}.csv", $report['rows']);
        }

        return ApiResponse::success($report);
    }

    public function employees(Request $request): Response
    {
        $rows = $this->reports->employeeDirectory();

        return $this->respond($request, $rows, 'employees.csv');
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,mixed>  $meta
     */
    private function respond(Request $request, array $rows, string $filename, array $meta = []): Response
    {
        if ($request->query('format') === 'csv') {
            return Csv::download($filename, $rows);
        }

        return ApiResponse::success($rows, array_merge(['count' => count($rows)], $meta));
    }
}
