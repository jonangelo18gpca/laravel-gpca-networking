<?php

namespace App\Http\Middleware;

use App\Models\Attendee;
use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class APICheckAttendeeExists
{
    use HttpResponses;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
//     public function handle(Request $request, Closure $next)
//     {
//         $eventId = (int) $request->route('eventId');
//         $attendeeId = (int) $request->route('attendeeId');

//         if (!is_numeric($eventId) || !is_numeric($attendeeId)) {
//             return $this->error(null, "Invalid event or attendee ID", 400);
//         }

//         $attendee = Attendee::where('id', $attendeeId)->where('event_id', $eventId)->where('is_active', true)->exists();

//         if (!$attendee) {
//             return $this->error(null, "Attendee doesn't exist or is not active for this event", 404);
//         }

//         // $authenticatedUser = Auth::user();
//         // if ($authenticatedUser->id !== $attendeeId) {
//         //     Log::warning("Unauthorized access attempt for attendee ID $attendeeId in event ID $eventId");
//         //     return $this->error(null, "Unauthorized access", 403);
//         // }

// $authenticatedUser = Auth::user();

// if (!$authenticatedUser) {
//     return $this->error(null, "Unauthorized", 401);
// }

// // Force use authenticated user
// if ($authenticatedUser->id !== $attendeeId) {
//     Log::warning("Route attendeeId mismatch. Using authenticated user instead.", [
//         'auth_id' => $authenticatedUser->id,
//         'route_id' => $attendeeId
//     ]);
// }

//         return $next($request);
//     }



public function handle(Request $request, Closure $next)
{
    $eventId = (int) $request->route('eventId');

    if (!is_numeric($eventId)) {
        return $this->error(null, "Invalid event ID", 400);
    }

    $authenticatedUser = Auth::user();

    if (!$authenticatedUser) {
        return $this->error(null, "Unauthorized", 401);
    }

    // 🔥 USE AUTH USER ONLY
    $attendee = Attendee::where('id', $authenticatedUser->id)
        ->where('event_id', $eventId)
        ->where('is_active', true)
        ->exists();

    if (!$attendee) {
        return $this->error(null, "Attendee not valid for this event", 404);
    }

    return $next($request);
}
}
