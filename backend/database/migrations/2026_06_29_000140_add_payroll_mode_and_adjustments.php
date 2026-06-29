<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll two-mode operation + period lock + adjustment audit (req #7).
 *
 *  - `mode` on a run: "payroll_only" (attendance entered/imported manually) vs
 *    "attendance_payroll" (attendance automatically drives loss-of-pay).
 *  - Reopen audit columns capture who reopened a locked run and when.
 *  - `payroll_adjustments` records every bonus, incentive, penalty, lock, and
 *    reopen as an immutable audit line tied to a run (and optionally employee).
 * See docs/06-MODULES.md (Payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('mode', 24)->default('attendance_payroll')->after('status'); // payroll_only|attendance_payroll
            $table->timestamp('locked_at')->nullable()->after('processed_at');
            $table->timestamp('reopened_at')->nullable()->after('locked_at');
            $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('type', 20); // bonus|incentive|penalty|other|lock|reopen|recalculate
            $table->string('label', 160);
            $table->bigInteger('amount')->default(0); // minor units; may be negative (penalty/deduction)
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['mode', 'locked_at', 'reopened_at']);
        });
    }
};
