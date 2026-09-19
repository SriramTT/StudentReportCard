<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the minimal authenticated dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        return view('dashboard', [
            'user' => $user,
        ]);
    }
}
