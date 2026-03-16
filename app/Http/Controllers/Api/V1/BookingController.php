<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmation;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\OfficeHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('appointment_date')
            ->paginate(50);
        return response()->json(['success' => true, 'data' => $bookings]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $booking = Booking::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed,no_show',
        ]);

        $booking->update(['status' => $request->status]);
        return response()->json(['success' => true, 'data' => $booking]);
    }

    public function availability(string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $officeHours = OfficeHour::where('agency_id', $agency->id)->get();

        $existingBookings = Booking::where('agency_id', $agency->id)
            ->where('appointment_date', '>=', now()->toDateString())
            ->where('appointment_date', '<=', now()->addDays(30)->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->get(['appointment_date', 'appointment_time', 'duration_minutes']);

        return response()->json([
            'success' => true,
            'data' => [
                'office_hours' => $officeHours,
                'booked_slots' => $existingBookings,
            ],
        ]);
    }

    public function embedConfig(string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $agency->name,
                'slug' => $agency->slug,
                'logo_url' => $agency->logo_url,
                'primary_color' => $agency->primary_color ?? '#2C4A5A',
                'accent_color' => $agency->accent_color ?? '#D4A855',
                'embed_theme' => $agency->embed_theme ?? 'light',
                'phone' => $agency->phone,
                'email' => $agency->email,
                'website' => $agency->website,
            ],
        ]);
    }

    public function book(Request $request, string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|string',
            'duration' => 'nullable|integer|min:15|max:120',
            'service_type' => 'nullable|string|max:100',
            'patient_first_name' => 'required|string|max:100',
            'patient_last_name' => 'required|string|max:100',
            'patient_email' => 'required|email',
            'patient_phone' => 'nullable|string|max:20',
            'patient_dob' => 'nullable|date',
            'insurance' => 'nullable|string|max:200',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::create([
            'agency_id' => $agency->id,
            'confirmation_code' => Booking::generateConfirmationCode($request->date),
            'appointment_date' => $request->date,
            'appointment_time' => $request->time,
            'duration_minutes' => $request->input('duration', 60),
            'service_type' => $request->service_type,
            'patient_first_name' => $request->patient_first_name,
            'patient_last_name' => $request->patient_last_name,
            'patient_email' => $request->patient_email,
            'patient_phone' => $request->patient_phone,
            'patient_dob' => $request->patient_dob,
            'insurance' => $request->insurance,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        // Send confirmation email
        if ($booking->patient_email) {
            Mail::to($booking->patient_email)->send(new BookingConfirmation($booking, $agency));
        }

        return response()->json(['success' => true, 'data' => $booking], 201);
    }
}
