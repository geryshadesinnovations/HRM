<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance improvements (req #6 & #7): break in/out tracking, derived
 * late/early minutes, free-text notes, and a `locked` flag set when the period
 * is closed by a published payroll run. Locked rows reject edits unless the
 * period is explicitly reopened. See docs/06-MODULES.md (Attendance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->timestamp('break_in')->nullable()->after('check_out');
            $table->timestamp('break_out')->nullable()->after('break_in');
            $table->integer('break_minutes')->default(0)->after('break_out');
            $table->integer('late_minutes')->default(0)->after('worked_minutes');
            $table->integer('early_minutes')->default(0)->after('late_minutes');
            $table->boolean('locked')->default(false)->after('source');
            $table->string('notes', 255)->nullable()->after('locked');

            $table->index(['company_id', 'locked']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'locked']);
            $table->dropColumn([
                'break_in', 'break_out', 'break_minutes',
                'late_minutes', 'early_minutes', 'locked', 'notes',
            ]);
        });
    }
};
