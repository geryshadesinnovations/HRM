<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance module: shifts + attendance_records.
 *
 * In production on PostgreSQL, `attendance_records` is intended to be
 * RANGE-partitioned by `work_date` (monthly) — see docs/02-DATABASE.md. The
 * migration creates a plain table so the same schema runs on SQLite (tests)
 * and Postgres; partitioning is applied as a Postgres-only operational step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 80);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('full_day_minutes')->default(480); // 8h
            $table->timestamps();
            $table->index('company_id');
        });

        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('work_date');
            $table->string('status', 15)->default('present'); // present|absent|half_day|leave|holiday
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->integer('worked_minutes')->nullable();
            $table->integer('overtime_minutes')->default(0);
            $table->string('source', 20)->default('manual'); // manual|self|gps|qr|biometric
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'work_date']);
            $table->index(['company_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('shifts');
    }
};
