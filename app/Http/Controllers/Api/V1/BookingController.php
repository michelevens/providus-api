<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\OfficeHour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    // Admin: list bookings for agency
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('date')
            ->paginate(50);
        return response()->json(['success' => true, 'data' => $bookings]);
    }

    // Admin: update booking status
    public function update(Request $request, int $id): JsonResponse
    {
        $booking = Booking::where('agency_id', $request->user()->agency_id)->findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed,no_show',
        ]);

        $booking->update(['status' => $request->status]);
        return response()->json(['success' => true, 'data' => $booking]);
    }

    // PUBLIC: Get available time slots for an agency
    public function availability(string $slug): JsonResponse
    {
        $agency = Agency::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $officeHours = OfficeHour::where('agency_id', $agency->id)->get();

        $existingBookings = Booking::where('agency_id', $agency->id)
            ->where('date', '>=', now()->toDateString())
            ->where('date', '<=', now()->addDays(30)->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->get(['date', 'time', 'duration']);

        return response()->json([
            'success' => true,
            'data' => [
                'office_hours' => $officeHours,
                'booked_slots' => $existingBookings,
            ],
        ]);
    }

    // PUBLIC: Book an appointment
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
            'insurance_payer' => 'nullable|string|max:100',
            'insurance_member_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $booking = Booking::create([
            'agency_id' => $agency->id,
            'confirmation_code' => strtoupper(Str::random(8)),
            'date' => $request->date,
            'time' => $request->time,
            'duration' => $request->input('duration', 60),
            'service_type' => $request->service_type,
            'patient_first_name' => $request->patient_first_name,
            'patient_last_name' => $request->patient_last_name,
            'patient_email' => $request->patient_email,
            'patient_phone' => $request->patient_phone,
            'patient_dob' => $request->patient_dob,
            'insurance_payer' => $request->insurance_payer,
            'insurance_member_id' => $request->insurance_member_id,
            'notes' => $request->notes,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'data' => $booking], 201);
    }
}
