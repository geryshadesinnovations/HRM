<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deep employee records (req #5). Expands the lean `employees` table into a
 * complete digital employee record: personal, contact, employment, and
 * salary/statutory information.
 *
 * Sensitive statutory identifiers (PAN, Aadhaar, bank account) are stored in
 * `text` columns so they can carry Laravel `encrypted` casts at the model
 * layer — see App\Domains\Employee\Models\Employee. See docs/02-DATABASE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            // Personal information
            $table->string('profile_photo_path')->nullable()->after('status');
            $table->string('gender', 20)->nullable()->after('profile_photo_path');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('blood_group', 8)->nullable()->after('date_of_birth');
            $table->string('marital_status', 20)->nullable()->after('blood_group');
            $table->string('nationality', 60)->nullable()->after('marital_status');

            // Contact information
            $table->string('emergency_contact_name', 120)->nullable()->after('nationality');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            $table->text('current_address')->nullable()->after('emergency_contact_phone');
            $table->text('permanent_address')->nullable()->after('current_address');

            // Employment information
            $table->string('employment_type', 30)->nullable()->after('permanent_address'); // full_time|part_time|contract|intern
            $table->string('work_location', 120)->nullable()->after('employment_type');
            $table->foreignId('shift_id')->nullable()->after('work_location')->constrained('shifts')->nullOnDelete();
            $table->date('confirmation_date')->nullable()->after('shift_id');
            $table->date('date_of_exit')->nullable()->after('confirmation_date');

            // Salary / statutory (sensitive identifiers are encrypted at model layer)
            $table->string('bank_account_name', 120)->nullable()->after('date_of_exit');
            $table->text('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc', 20)->nullable()->after('bank_account_number');
            $table->text('pan')->nullable()->after('bank_ifsc');
            $table->text('aadhaar')->nullable()->after('pan');
            $table->string('uan', 40)->nullable()->after('aadhaar');
            $table->string('pf_number', 40)->nullable()->after('uan');
            $table->string('esi_number', 40)->nullable()->after('pf_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn([
                'profile_photo_path', 'gender', 'date_of_birth', 'blood_group',
                'marital_status', 'nationality', 'emergency_contact_name',
                'emergency_contact_phone', 'current_address', 'permanent_address',
                'employment_type', 'work_location', 'confirmation_date', 'date_of_exit',
                'bank_account_name', 'bank_account_number', 'bank_ifsc', 'pan',
                'aadhaar', 'uan', 'pf_number', 'esi_number',
            ]);
        });
    }
};
