<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications. One row per recipient user. Queried only by user_id, so
 * a user can never see another user's notifications. company_id is stored for
 * reference/reporting. See docs/09-REPORTING-NOTIFICATIONS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('type', 60);          // e.g. leave.approved, payroll.payslip, billing.payment
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->json('data')->default('{}');  // arbitrary context (uuids, links)
            $table->string('channel', 20)->default('in_app');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
