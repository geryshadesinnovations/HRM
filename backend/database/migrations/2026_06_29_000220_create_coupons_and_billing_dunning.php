<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coupons / discount engine + billing dunning & auto-renew support (req #1/#3).
 *
 *  - `coupons` (platform-level): percent or fixed discounts, optionally scoped
 *    to a plan, time-boxed, with a redemption cap.
 *  - `coupon_redemptions`: audit of which company redeemed what, and how much.
 *  - invoices gain discount + dunning columns; subscriptions track dunning
 *    attempts so the billing cycle command can retry and escalate.
 * See docs/04-BILLING.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('code', 40)->unique();
            $table->string('description', 200)->nullable();
            $table->string('type', 10); // percent|fixed
            $table->bigInteger('value'); // percent (whole number) OR fixed amount in minor units
            $table->char('currency', 3)->default('INR');
            $table->string('plan_code', 40)->nullable(); // restrict to a plan, or null = any
            $table->integer('max_redemptions')->nullable();
            $table->integer('redemptions_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->bigInteger('amount_discounted')->default(0);
            $table->timestamps();

            $table->index(['coupon_id', 'company_id']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->bigInteger('discount_total')->default(0)->after('tax_total');
            $table->foreignId('coupon_id')->nullable()->after('subscription_id')->constrained('coupons')->nullOnDelete();
            $table->integer('attempts')->default(0)->after('due_at');       // dunning retries
            $table->timestamp('next_attempt_at')->nullable()->after('attempts');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->integer('dunning_attempts')->default(0)->after('auto_renew');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('dunning_attempts');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['discount_total', 'attempts', 'next_attempt_at']);
        });

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
