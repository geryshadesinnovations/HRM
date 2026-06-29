<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee document vault (req #5). Securely stores employee documents
 * (resume, offer letter, contract, ID proofs, certificates, etc.).
 *
 * Documents are versioned per (employee, type): re-uploading the same type
 * creates a new row with an incremented `version` rather than overwriting, so
 * the full history is retained. Binary content lives on a private disk; only
 * the metadata + storage path are persisted here. Tenant-scoped by company_id.
 * See docs/02-DATABASE.md and docs/08-SECURITY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('type', 40); // resume|offer_letter|contract|aadhaar|pan|passport|license|education|experience|payslip|bank_passbook|photo|other
            $table->string('title', 160);
            $table->string('original_name', 255);
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
