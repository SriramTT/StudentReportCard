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
                <h1>School Examination System</h1>
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

            <form method="POST" action="{{ route('login.submit') }}" novalidate>
                @csrf

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control @error('username') is-invalid @enderror"
                        value="{{ old('username') }}"
                        required
                        autofocus
                        autocomplete="username"
                        maxlength="50"
                    >
                    @error('username')
                        <div class="form-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required
                        autocomplete="current-password"
                    >
                    @error('password')
                        <div class="form-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
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
</body>
</html>
