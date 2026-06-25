<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll module: salary_components, salary_structures (+lines), payroll_runs,
 * payslips. Money in minor units (paise). See docs/02-DATABASE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('type', 12); // earning|deduction
            $table->boolean('is_taxable')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });

        Schema::create('salary_structures', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->bigInteger('ctc_monthly')->default(0); // informational, minor units
            $table->timestamps();

            $table->unique(['company_id', 'employee_id']);
        });

        Schema::create('salary_structure_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained('salary_structures')->cascadeOnDelete();
            $table->foreignId('salary_component_id')->constrained('salary_components')->cascadeOnDelete();
            $table->bigInteger('amount'); // monthly amount in minor units
            $table->timestamps();

            $table->index('salary_structure_id');
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 15)->default('draft'); // draft|processing|completed|locked
            $table->bigInteger('total_gross')->nullable();
            $table->bigInteger('total_deductions')->nullable();
            $table->bigInteger('total_net')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period_year', 'period_month']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->bigInteger('gross');
            $table->bigInteger('deductions');
            $table->bigInteger('net');
            $table->decimal('worked_days', 4, 1)->nullable();
            $table->decimal('lop_days', 4, 1)->default(0);
            $table->json('breakdown')->default('{}');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('salary_structure_lines');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('salary_components');
    }
};
