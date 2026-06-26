<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Platform\Models\ContactInquiry;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super-admin management of public "Contact Us" submissions (req #1).
 */
final class AdminContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactInquiry::query();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('id')->paginate(min((int) $request->integer('per_page', 20), 100));

        return ApiResponse::paginated($paginator);
    }

    public function update(Request $request, ContactInquiry $inquiry): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(ContactInquiry::STATUSES)],
        ]);

        $inquiry->update($data);

        return ApiResponse::success($inquiry);
    }
}
