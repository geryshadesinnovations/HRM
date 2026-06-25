<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing: invoices, invoice_lines, payments, webhook_events.
 * Money in minor units. See docs/02-DATABASE.md and docs/04-BILLING.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('number', 40)->unique();         // INV-2026-000123
            $table->string('status', 20)->default('draft');  // draft|open|paid|void|uncollectible
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->char('currency', 3)->default('INR');
            $table->string('gstin', 20)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->default('{}');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 200);
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_amount');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('line_total');
            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('gateway', 30);                   // razorpay|cashfree|payu|manual
            $table->string('gateway_payment_id', 120)->nullable();
            $table->string('status', 20)->default('created'); // created|authorized|captured|failed|refunded
            $table->bigInteger('amount');
            $table->char('currency', 3)->default('INR');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('gateway', 30);
            $table->string('event_id', 160);
            $table->string('type', 80);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']); // idempotency guard
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
