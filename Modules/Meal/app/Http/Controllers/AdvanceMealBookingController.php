<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Meal\Models\AdvanceMealBooking;
use Modules\Meal\Models\MealType;

class AdvanceMealBookingController extends Controller
{
    /**
     * Get meal types + existing bookings
     */
    public function index(Request $request)
    {
        $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        $employeeId = Auth::user()->employee_id;

        if (!$employeeId) {
            return response()->json([
                'message' => 'Employee profile not found.'
            ], 422);
        }

        $fromDate = Carbon::parse($request->from_date)->startOfDay();
        $toDate = Carbon::parse($request->to_date)->endOfDay();

        $mealTypes = MealType::query()
            ->select([
                'id',
                'name',
                'booking_cutoff_time',
            ])
            ->orderBy('id')
            ->get();

        $bookings = AdvanceMealBooking::query()
            ->with('mealType')
            ->where('employee_id', $employeeId)
            ->whereBetween('booking_date', [
                $fromDate->toDateString(),
                $toDate->toDateString(),
            ])
            ->orderBy('booking_date')
            ->get();

        return response()->json([
            'meal_types' => $mealTypes,
            'bookings' => $bookings,
        ]);
    }


    /**
     * Create / Update advance bookings
     */
    public function store(Request $request)
    {
        $request->validate([
            'bookings' => ['required', 'array', 'min:1'],

            'bookings.*.booking_date' => [
                'required',
                'date',
            ],

            'bookings.*.meal_type_id' => [
                'required',
                'integer',
                'exists:meal_types,id',
            ],

            'bookings.*.status' => [
                'required',
                'in:booked,cancelled',
            ],
        ]);

        $employeeId = Auth::user()->employee_id;

        if (!$employeeId) {
            return response()->json([
                'message' => 'Employee profile not found.'
            ], 422);
        }

        DB::transaction(function () use ($request, $employeeId) {

            foreach ($request->bookings as $item) {

                $date = Carbon::parse(
                    $item['booking_date']
                )->startOfDay();

                $mealType = MealType::findOrFail(
                    $item['meal_type_id']
                );

                /*
                 * Check cutoff
                 */
                $this->ensureBeforeCutoff(
                    $date,
                    $mealType
                );

                $booking = AdvanceMealBooking::updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'meal_type_id' => $mealType->id,
                        'booking_date' => $date->toDateString(),
                    ],
                    [
                        'status' => $item['status'],
                    ]
                );

                /*
                 * If cancelled, keep record but mark cancelled.
                 */
                if ($item['status'] === 'cancelled') {
                    $booking->update([
                        'status' => 'cancelled',
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Advance meal booking updated successfully.'
        ]);
    }


    /**
     * Delete/cancel one booking
     */
    public function destroy(
        Request $request,
        AdvanceMealBooking $booking
    ) {
        $employeeId = Auth::user()->employee_id;

        if ($booking->employee_id !== $employeeId) {
            return response()->json([
                'message' => 'Unauthorized.'
            ], 403);
        }

        $mealType = $booking->mealType;

        $this->ensureBeforeCutoff(
            Carbon::parse($booking->booking_date),
            $mealType
        );

        $booking->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'message' => 'Meal booking cancelled successfully.'
        ]);
    }


    /**
     * Cutoff validation
     */
    private function ensureBeforeCutoff(
        Carbon $bookingDate,
        MealType $mealType
    ): void {

        $cutoff = $mealType->booking_cutoff_time;

        if (!$cutoff) {
            return;
        }

        /*
         * Booking date + meal cutoff time
         *
         * Example:
         * Lunch
         * Date: 26 Aug
         * Cutoff: 11:00 AM
         */

        $cutoffDateTime = Carbon::parse(
            $bookingDate->format('Y-m-d') .
            ' ' .
            $cutoff
        );

        if (now()->greaterThanOrEqualTo($cutoffDateTime)) {

            throw ValidationException::withMessages([
                'booking' => [
                    "{$mealType->name} booking is closed for " .
                    $bookingDate->format('d M Y') .
                    ". Cutoff time was " .
                    $cutoffDateTime->format('h:i A') . "."
                ]
            ]);
        }
    }
}