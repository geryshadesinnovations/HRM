<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name', 180);
            $table->string('slug', 120)->unique();
            $table->string('status', 20)->default('active'); // active|suspended|cancelled
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->char('currency', 3)->default('INR');
            $table->char('country', 2)->default('IN');
            $table->string('gstin', 20)->nullable();
            $table->json('settings')->default('{}');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
