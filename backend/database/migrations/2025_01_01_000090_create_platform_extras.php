<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform extras:
 *  - Richer company profile fields captured at registration (req #3).
 *  - contact_inquiries for the public "Contact Us" form (req #1/#2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('name');
            $table->string('industry', 80)->nullable()->after('phone');
            $table->unsignedInteger('employees_estimate')->nullable()->after('industry');
            $table->text('address')->nullable()->after('employees_estimate');
        });

        Schema::create('contact_inquiries', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name', 120);
            $table->string('company_name', 180)->nullable();
            $table->string('email', 160);
            $table->string('phone', 30)->nullable();
            $table->string('subject', 180)->nullable();
            $table->text('message');
            $table->string('status', 15)->default('new'); // new|contacted|closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'industry', 'employees_estimate', 'address']);
        });
    }
};
