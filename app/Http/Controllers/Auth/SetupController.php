<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SystemAlreadyInitializedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetupRequest;
use App\Services\SystemSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function __construct(
        protected SystemSetupService $setupService
    ) {}

    /**
     * Display the first-run Administrator setup form.
     * Accessible only when the database contains exactly zero users.
     */
    public function showSetupForm(): View|RedirectResponse
    {
        if (! $this->setupService->isSetupAvailable()) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    /**
     * Handle the first-run Administrator account creation.
     */
    public function setup(SetupRequest $request): RedirectResponse
    {
        if (! $this->setupService->isSetupAvailable()) {
            return redirect()
                ->route('login')
                ->withErrors(['username' => 'System setup has already been completed.']);
        }

        try {
            $user = $this->setupService->createInitialAdministrator(
                data: $request->validated(),
                ipAddress: $request->ip()
            );

            // Authenticate newly created Administrator with session regeneration (TH-07)
            Auth::login($user);
            $request->session()->regenerate();

            // Record initial login timestamp
            $user->forceFill(['last_login_at' => now()])->save();

            return redirect()->route('dashboard');
        } catch (SystemAlreadyInitializedException $e) {
            return redirect()
                ->route('login')
                ->withErrors(['username' => 'System setup has already been completed.']);
        }
    }
}
