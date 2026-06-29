<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GPS + biometric attendance capture (req #6).
 *
 *  - `biometric_devices`: physical/virtual punch devices registered per tenant,
 *    authenticated by a hashed token. A device pushes punches for an employee
 *    code without a user session.
 *  - Attendance gains optional geo-coordinates per punch and a `capture_method`
 *    (web | gps | biometric) plus the originating device.
 *
 * GPS capture is gated by the `attendance.gps` feature and biometric by
 * `attendance.biometric`. See docs/06-MODULES.md (Attendance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('serial', 120)->nullable();
            $table->string('location', 160)->nullable();
            $table->string('token_hash', 64)->unique(); // sha256 of the device token
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->string('capture_method', 12)->default('web')->after('source'); // web|gps|biometric
            $table->decimal('check_in_lat', 10, 7)->nullable()->after('check_in');
            $table->decimal('check_in_lng', 10, 7)->nullable()->after('check_in_lat');
            $table->decimal('check_out_lat', 10, 7)->nullable()->after('check_out');
            $table->decimal('check_out_lng', 10, 7)->nullable()->after('check_out_lat');
            $table->foreignId('biometric_device_id')->nullable()->after('capture_method')
                ->constrained('biometric_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('biometric_device_id');
            $table->dropColumn([
                'capture_method', 'check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng',
            ]);
        });

        Schema::dropIfExists('biometric_devices');
    }
};
