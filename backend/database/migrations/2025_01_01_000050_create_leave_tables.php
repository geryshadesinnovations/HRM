<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave module: leave_types, leave_balances, leave_requests, approvals.
 * See docs/02-DATABASE.md and docs/06-MODULES.md (Leave Module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 80);
            $table->boolean('is_paid')->default(true);
            $table->decimal('annual_quota', 5, 1)->default(0);     // days/year
            $table->boolean('carry_forward')->default(false);
            $table->decimal('carry_forward_cap', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
        });

        Schema::create('leave_balances', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated', 6, 1)->default(0);
            $table->decimal('used', 6, 1)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'leave_type_id', 'year']);
            $table->index(['company_id', 'employee_id']);
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 4, 1);
            $table->string('status', 15)->default('pending'); // pending|approved|rejected|cancelled
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'start_date']);
            $table->index(['company_id', 'employee_id']);
        });

        Schema::create('leave_request_approvals', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 15); // approved|rejected
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('leave_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approvals');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
