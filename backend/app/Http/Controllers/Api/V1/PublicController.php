<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Platform\Models\ContactInquiry;
use App\Domains\Subscription\Models\Plan;
use App\Http\Controllers\Controller;
use App\Platform\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unauthenticated endpoints for the public marketing website (req #2):
 * dynamic pricing and the contact form.
 */
final class PublicController extends Controller
{
    /** Public plan catalogue — powers the landing-page pricing section. */
    public function plans(): JsonResponse
    {
        $plans = Plan::with('modules:id,code,name')
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('base_price')
            ->get()
            ->map(fn (Plan $p) => [
                'code' => $p->code,
                'name' => $p->name,
                'billing_cycle' => $p->billing_cycle,
                'base_price' => $p->base_price,
                'currency' => $p->currency,
                'included_seats' => $p->included_seats,
                'per_seat_price' => $p->per_seat_price,
                'trial_days' => $p->trial_days,
                'modules' => $p->modules->pluck('name'),
            ]);

        return ApiResponse::success($plans);
    }

    /** Public "Contact Us" submission. */
    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company_name' => ['nullable', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactInquiry::create(array_merge($data, ['status' => 'new']));

        return ApiResponse::success(['message' => 'Thanks! We will get back to you shortly.'], status: 201);
    }
}
