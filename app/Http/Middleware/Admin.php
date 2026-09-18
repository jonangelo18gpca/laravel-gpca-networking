<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class Admin
{
    public function handle(Request $request, Closure $next)
    {
        $userType = Session::get('userType');

        if (!in_array($userType, ['gpcaAdmin', 'editor'], true)) {
            return redirect('admin/login');
        }

        if ($userType === 'editor') {
            $allowedPaths = [
                'admin/dashboard',
                'admin/event',

                'admin/event/*/*/dashboard',


                'admin/event/*/*/speaker',
                'admin/event/*/*/speaker/*',

                'admin/event/*/*/session',
                'admin/event/*/*/session/*',

                'admin/event/*/*/sponsor',
                'admin/event/*/*/sponsor/*',

                'admin/event/*/*/exhibitor',
                'admin/event/*/*/exhibitor/*',

                'admin/event/*/*/meeting-room-partner',
                'admin/event/*/*/meeting-room-partner/*',

                'admin/event/*/*/media-partner',
                'admin/event/*/*/media-partner/*',

                'admin/event/*/*/notification',

                'admin/logout',
            ];

            if (!$request->is($allowedPaths)) {
                abort(403, 'You do not have permission to access this page.');
            }
        }

        return $next($request);
    }
}
