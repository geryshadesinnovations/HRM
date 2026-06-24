<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription domain: modules, features, plans, plan↔module, plan↔feature,
 * subscriptions. See docs/02-DATABASE.md and docs/03-SUBSCRIPTION.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('module_id')->nullable()->constrained('modules')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('name', 140);
            $table->string('type', 20)->default('boolean'); // boolean|limit|quota
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('billing_cycle', 20)->default('monthly'); // monthly|yearly
            $table->bigInteger('base_price')->default(0);            // minor units
            $table->char('currency', 3)->default('INR');
            $table->integer('included_seats')->default(0);
            $table->bigInteger('per_seat_price')->default(0);
            $table->integer('trial_days')->default(0);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_module', function (Blueprint $table): void {
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->primary(['plan_id', 'module_id']);
        });

        Schema::create('plan_feature', function (Blueprint $table): void {
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->string('value', 80)->default('true'); // 'true' | '100' | 'unlimited'
            $table->primary(['plan_id', 'feature_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status', 20)->default('trial');
            $table->integer('seats')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->json('overrides')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index(['status', 'current_period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_feature');
        Schema::dropIfExists('plan_module');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('features');
        Schema::dropIfExists('modules');
    }
};
