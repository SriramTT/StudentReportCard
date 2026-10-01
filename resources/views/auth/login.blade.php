<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School Examination Marks and Report Card Management System - Sign In">
    <title>Sign In - School Examination & Report Card Management System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-wrapper">
        <div class="auth-card">
            <header class="auth-header">
                @if (!empty($schoolLogoUrl) && !empty($schoolName))
                    <div style="margin-bottom: 1rem; display: flex; justify-content: center;">
                        <img src="{{ $schoolLogoUrl }}" alt="{{ $schoolName }} Logo" class="auth-logo" style="max-height: 64px; max-width: 220px; object-fit: contain;">
                    </div>
                    <h1>{{ $schoolName }}</h1>
                @elseif (!empty($schoolLogoUrl))
                    <div style="margin-bottom: 1rem; display: flex; justify-content: center;">
                        <img src="{{ $schoolLogoUrl }}" alt="School Logo" class="auth-logo" style="max-height: 64px; max-width: 220px; object-fit: contain;">
                    </div>
                @elseif (!empty($schoolName))
                    <h1>{{ $schoolName }}</h1>
                @else
                    <h1>School Examination System</h1>
                @endif
                <p>Please enter your credentials to access the system</p>
            </header>

            @if (session('status'))
                <div class="alert alert-success" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @php
                $hasCredentialError = $errors->has('credentials')
                    || ($errors->has('username') && $errors->first('username') === 'These credentials do not match our records.')
                    || ($errors->has('password') && $errors->first('password') === 'These credentials do not match our records.');
            @endphp

            @if ($hasCredentialError)
                <div class="alert alert-danger" role="alert" style="margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>{{ $errors->first('credentials') ?: 'Invalid username or password. Please verify your credentials and try again.' }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" novalidate>
                @csrf

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control @if($errors->has('username') || $hasCredentialError) is-invalid @endif"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                        maxlength="50"
                        @if($errors->has('username') || $hasCredentialError) aria-invalid="true" @endif
                        @if($errors->has('username')) aria-describedby="username-error" @endif
                    >
                    @if ($errors->has('username') && $errors->first('username') !== 'These credentials do not match our records.' && $errors->first('username') !== 'Invalid username or password. Please verify your credentials and try again.')
                        <div class="form-error" role="alert" id="username-error" style="display: flex; align-items: center; gap: 0.35rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>{{ $errors->first('username') }}</span>
                        </div>
                    @endif
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control @if($errors->has('password') || $hasCredentialError) is-invalid @endif"
                            required
                            autocomplete="current-password"
                            style="padding-right: 2.75rem;"
                            @if($errors->has('password') || $hasCredentialError) aria-invalid="true" @endif
                            @if($errors->has('password')) aria-describedby="password-error" @endif
                        >
                        <button
                            type="button"
                            id="toggle-password-btn"
                            class="password-toggle-btn"
                            aria-label="Toggle password visibility"
                            title="Toggle password visibility"
                            style="position: absolute; right: 0.5rem; background: transparent; border: none; cursor: pointer; padding: 0.375rem; color: var(--color-text-muted, #6b7280); display: flex; align-items: center; justify-content: center; border-radius: 4px;"
                        >
                            <!-- Eye icon (show password) -->
                            <svg id="eye-icon-show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="7" r="3"></circle>
                            </svg>
                            <!-- Eye off icon (hide password) -->
                            <svg id="eye-icon-hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                    @if ($errors->has('password') && $errors->first('password') !== 'These credentials do not match our records.' && $errors->first('password') !== 'Invalid username or password. Please verify your credentials and try again.')
                        <div class="form-error" role="alert" id="password-error" style="display: flex; align-items: center; gap: 0.35rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>{{ $errors->first('password') }}</span>
                        </div>
                    @endif
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" id="login-submit-button">
                        Sign In
                    </button>
                </div>
            </form>

            @if (!empty($setupAvailable))
                <div style="margin-top: 1.5rem; text-align: center; border-top: 1px solid var(--color-border); padding-top: 1.25rem;">
                    <p style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: 0.75rem;">
                        Initial system setup required
                    </p>
                    <a href="{{ route('setup') }}" class="btn" id="create-admin-button" style="background-color: var(--color-primary-light); color: var(--color-primary); border: 1px solid var(--color-primary); text-decoration: none; font-weight: 600;">
                        Create Administrator Account
                    </a>
                </div>
            @endif
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('toggle-password-btn');
        const passwordInput = document.getElementById('password');
        const eyeShow = document.getElementById('eye-icon-show');
        const eyeHide = document.getElementById('eye-icon-hide');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                if (eyeShow && eyeHide) {
                    eyeShow.style.display = isPassword ? 'none' : 'block';
                    eyeHide.style.display = isPassword ? 'block' : 'none';
                }
                toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            });
        }
    });
    </script>
</body>
</html>
