<?php

declare(strict_types=1);

namespace App\Domains\Employee\Services;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\EmployeeDocument;
use App\Platform\Exceptions\ApiException;
use App\Platform\Support\ErrorCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure, versioned employee document vault (req #5).
 *
 * Files are stored on a private disk under a tenant- and employee-scoped path
 * so they are never publicly reachable; downloads stream through an authorized
 * controller action. Re-uploading the same `type` increments the version.
 */
final class EmployeeDocumentService
{
    /** Private disk used for the vault (configurable via FILESYSTEM_DISK). */
    private function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    public function store(Employee $employee, string $type, UploadedFile $file, ?string $title, ?int $userId): EmployeeDocument
    {
        if (! in_array($type, EmployeeDocument::TYPES, true)) {
            throw new ApiException(
                ErrorCode::ValidationFailed,
                'Invalid document type.',
                ['type' => EmployeeDocument::TYPES],
                422,
            );
        }

        $disk = $this->disk();
        $companyId = (int) $employee->company_id;
        $dir = "employee-documents/{$companyId}/{$employee->id}";

        // Next version for this (employee, type) — includes soft-deleted history.
        $version = (int) EmployeeDocument::withTrashed()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->max('version') + 1;

        $path = $file->store($dir, $disk);
        if ($path === false) {
            throw new ApiException(ErrorCode::ServerError, 'Failed to store the document.', [], 500);
        }

        return DB::transaction(fn () => EmployeeDocument::create([
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'type' => $type,
            'title' => $title ?: $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'version' => $version,
            'uploaded_by' => $userId,
        ]));
    }

    /**
     * Stream a stored document as a download response, enforcing tenant scope.
     */
    public function download(EmployeeDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);

        if (! $disk->exists($document->path)) {
            throw new ApiException(ErrorCode::NotFound, 'The document file is missing.', [], 404);
        }

        return $disk->download($document->path, $document->original_name);
    }

    /**
     * Soft-delete the metadata row and remove the underlying file from disk.
     */
    public function delete(EmployeeDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->delete(); // soft delete preserves audit history

            Storage::disk($document->disk)->delete($document->path);
        });
    }
}
