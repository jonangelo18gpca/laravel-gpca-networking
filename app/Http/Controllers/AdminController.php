<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class AdminController extends Controller
{
    // public function loginView()
    // {
    //     if (Session::has('userType')) {
    //         if (Session::get('userType') == 'gpcaAdmin') {
    //             return redirect('/admin/dashboard');
    //         }
    //     }
    //     return view('admin.login.login');
    // }

    public function loginView()
{
    if (in_array(Session::get('userType'), ['gpcaAdmin', 'editor'], true)) {
        return redirect('/admin/dashboard');
    }

    return view('admin.login.login');
}


    // RENDER LOGICS
    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'username' => 'required',
    //         'password' => 'required'
    //     ]);
    //     if ($request->username == env('ADMIN_USERNAME') && $request->password == env('ADMIN_PASSWORD')) {
    //         $request->session()->put('userType', 'gpcaAdmin');
    //         return Redirect::to("/admin/event")->withSuccess('Welcome');
    //     } else {
    //         return Redirect::to("/admin/login")->withFail('Invalid username & password!');
    //     }
    // }


    public function login(Request $request)
{
    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    if (
        $request->username === env('ADMIN_USERNAME') &&
        $request->password === env('ADMIN_PASSWORD')
    ) {
        $request->session()->regenerate();
        $request->session()->put('userType', 'gpcaAdmin');

        return Redirect::to('/admin/dashboard')->withSuccess('Welcome');
    }

    if (
        $request->username === env('EDITOR_USERNAME') &&
        $request->password === env('EDITOR_PASSWORD')
    ) {
        $request->session()->regenerate();
        $request->session()->put('userType', 'editor');

        return Redirect::to('/admin/dashboard')->withSuccess('Welcome');
    }

    return Redirect::to('/admin/login')
        ->withFail('Invalid username & password!');
}

    public function logout()
    {
        Session::flush();
        return Redirect::to("/admin/login")->withSuccess('Logged out successfully');
    }







    // =========================================================
    //                       API FUNCTIONS
    // =========================================================
    public function apiLatestAppVersion(Request $request)
    {
        $platform = $request->query('platform');

        if ($platform === 'android') {
            return response()->json([
                'version' => '2.5.0',
                'force_update' => false,
                'update_url' => 'https://play.google.com/store/apps/details?id=com.gpcanetworking2.app',
            ]);
        }

        if ($platform === 'ios') {
            return response()->json([
                'version' => '2.5.0',
                'force_update' => false,
                'update_url' => 'https://apps.apple.com/us/app/gpca-events-networking/id6639614793',
            ]);
        }

        return response()->json(['message' => 'Invalid platform.'], 400);
    }
}
