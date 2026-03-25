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
    public function handle(Request $request, Closure $next)
    {
        $eventId = (int) $request->route('eventId');
        $attendeeId = (int) $request->route('attendeeId');

        if (!is_numeric($eventId) || !is_numeric($attendeeId)) {
            return $this->error(null, "Invalid event or attendee ID", 400);
        }

        $attendee = Attendee::where('id', $attendeeId)->where('event_id', $eventId)->where('is_active', true)->exists();

        if (!$attendee) {
            return $this->error(null, "Attendee doesn't exist or is not active for this event", 404);
        }

        $authenticatedUser = Auth::user();
        if ($authenticatedUser->id !== $attendeeId) {
            Log::warning("Unauthorized access attempt for attendee ID $attendeeId in event ID $eventId");
            return $this->error(null, "Unauthorized access", 403);
        }

        return $next($request);
    }
}
