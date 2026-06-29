<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Models\EmployeeDocument;
use App\Domains\Employee\Services\EmployeeDocumentService;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee document vault API (req #5). Tenant-scoped; viewing requires
 * `employee.document.view`, mutating requires `employee.document.manage`.
 * See docs/07-API.md (Employee Documents).
 */
final class EmployeeDocumentController extends Controller
{
    public function __construct(private readonly EmployeeDocumentService $service) {}

    /** List an employee's documents (latest version first). */
    public function index(Employee $employee): JsonResponse
    {
        $docs = $employee->documents()
            ->orderBy('type')
            ->orderByDesc('version')
            ->get()
            ->map(fn (EmployeeDocument $d) => $this->present($d));

        return ApiResponse::success($docs);
    }

    /** Upload a new document (auto-versioned per type). */
    public function store(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(EmployeeDocument::TYPES)],
            'title' => ['nullable', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:10240', // 10 MB
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv'],
        ]);

        $document = $this->service->store(
            $employee,
            $data['type'],
            $request->file('file'),
            $data['title'] ?? null,
            $request->user()?->getKey(),
        );

        return ApiResponse::success($this->present($document), status: 201);
    }

    /** Stream a document file as an authorized download. */
    public function download(EmployeeDocument $document): StreamedResponse
    {
        return $this->service->download($document);
    }

    /** Remove a document (soft-deletes metadata + deletes the file). */
    public function destroy(EmployeeDocument $document): JsonResponse
    {
        $this->service->delete($document);

        return ApiResponse::success(['message' => 'Document removed.']);
    }

    /** @return array<string,mixed> */
    private function present(EmployeeDocument $d): array
    {
        return [
            'uuid' => $d->uuid,
            'type' => $d->type,
            'title' => $d->title,
            'original_name' => $d->original_name,
            'mime' => $d->mime,
            'size' => $d->size,
            'version' => $d->version,
            'uploaded_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
