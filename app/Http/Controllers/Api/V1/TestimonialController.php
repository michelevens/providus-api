<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\TestimonialRequest;
use App\Models\Agency;
use App\Models\Testimonial;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TestimonialController extends Controller
{
    // Admin: list all testimonials
    public function index(Request $request): JsonResponse
    {
        $testimonials = Testimonial::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $testimonials]);
    }

    // Admin: update testimonial status (approve/reject)
    public function update(Request $request, int $id): JsonResponse
    {
        $testimonial = Testimonial::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $testimonial->update(['status' => $request->status]);
        return response()->json(['success' => true, 'data' => $testimonial]);
    }

    // Admin: generate a review invite link
    public function generateLink(Request $request): JsonResponse
    {
        $request->validate([
            'patient_first_name' => 'required|string|max:100',
            'patient_last_name' => 'required|string|max:100',
            'patient_email' => 'nullable|email',
        ]);

        $testimonial = Testimonial::create([
            'agency_id' => $request->user()->agency_id,
            'token' => Str::random(32),
            'patient_first_name' => $request->patient_first_name,
            'patient_last_name' => $request->patient_last_name,
            'patient_email' => $request->patient_email,
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        // Send review request email if patient email provided
        if ($request->patient_email) {
            $agency = $request->user()->agency;
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'https://app.credentik.com'));
            $reviewUrl = "{$frontendUrl}/#review/{$testimonial->token}";
            Mail::to($request->patient_email)->send(new TestimonialRequest($testimonial, $agency, $reviewUrl));
        }

        return response()->json(['success' => true, 'data' => $testimonial], 201);
    }

    // PUBLIC: Get approved testimonials for an agency
    public function publicIndex(string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $testimonials = Testimonial::where('agency_id', $agency->id)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get(['display_name', 'rating', 'text', 'created_at']);

        return response()->json(['success' => true, 'data' => $testimonials]);
    }

    // PUBLIC: Get testimonial form by token
    public function showByToken(string $token): JsonResponse
    {
        $testimonial = Testimonial::where('token', $token)->firstOrFail();

        if ($testimonial->text) {
            return response()->json(['success' => false, 'error' => 'Review already submitted'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'patient_first_name' => $testimonial->patient_first_name,
                'agency_name' => $testimonial->agency->name,
            ],
        ]);
    }

    // PUBLIC: Submit a review
    public function submitByToken(Request $request, string $token): JsonResponse
    {
        $testimonial = Testimonial::where('token', $token)->firstOrFail();

        if ($testimonial->text) {
            return response()->json(['success' => false, 'error' => 'Review already submitted'], 400);
        }

        $request->validate([
            'display_name' => 'required|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'text' => 'required|string|max:2000',
        ]);

        $testimonial->update([
            'display_name' => $request->display_name,
            'rating' => $request->rating,
            'text' => $request->text,
        ]);

        // Notify agency of new review
        NotificationService::send($testimonial->agency_id, 'review_submitted', 'New Patient Review', [
            'body' => "{$request->display_name} left a {$request->rating}-star review",
            'link' => 'testimonials',
            'linkable_type' => 'testimonial',
            'linkable_id' => $testimonial->id,
        ]);

        return response()->json(['success' => true, 'data' => $testimonial]);
    }
}
