<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->foreignId('company_id')->nullable()->after('uuid')
                ->constrained('companies')->nullOnDelete();
            $table->boolean('is_super_admin')->default(false)->after('email');

            $table->index(['company_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['uuid', 'is_super_admin']);
        });
    }
};
