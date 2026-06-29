<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-company statutory payroll configuration (req #7): PF, ESI, and TDS
 * (income tax) rates and toggles. Wage ceilings are in minor units (paise).
 * Defaults reflect common Indian statutory values and are fully editable.
 * See docs/06-MODULES.md (Payroll → Statutory).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();

            // Provident Fund
            $table->boolean('pf_enabled')->default(true);
            $table->decimal('pf_employee_rate', 5, 2)->default(12.00);
            $table->decimal('pf_employer_rate', 5, 2)->default(12.00);
            $table->bigInteger('pf_wage_ceiling')->default(1500000); // ₹15,000

            // Employees' State Insurance
            $table->boolean('esi_enabled')->default(true);
            $table->decimal('esi_employee_rate', 5, 2)->default(0.75);
            $table->decimal('esi_employer_rate', 5, 2)->default(3.25);
            $table->bigInteger('esi_wage_ceiling')->default(2100000); // ₹21,000 gross

            // Income tax (TDS) — simplified slab estimator
            $table->boolean('tds_enabled')->default(false);
            $table->string('tds_regime', 10)->default('new'); // new|old
            $table->bigInteger('tds_standard_deduction')->default(5000000); // ₹50,000/yr

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
