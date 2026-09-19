<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Initial Administrator Setup - School Examination Marks and Report Card Management System">
    <title>Initial Setup - School Examination & Report Card Management System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-wrapper">
        <div class="auth-card" style="max-width: 30rem;">
            <header class="auth-header">
                <h1>Initial System Setup</h1>
                <p>Create the initial Administrator account</p>
            </header>

            <div class="alert" style="background-color: var(--color-primary-light); color: var(--color-primary); border-color: #bfdbfe; font-size: var(--font-size-sm); margin-bottom: 1.25rem;">
                <strong>First-Run Bootstrap:</strong> This setup is available only when zero accounts exist. The created account is granted full Administrator privileges. Subsequent users must be created through User Management.
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    Please correct the errors below and try again.
                </div>
            @endif

            <form method="POST" action="{{ route('setup.submit') }}" novalidate>
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
                    <label for="display_name" class="form-label">Display Name / Full Name</label>
                    <input
                        type="text"
                        id="display_name"
                        name="display_name"
                        class="form-control @error('display_name') is-invalid @enderror"
                        value="{{ old('display_name') }}"
                        required
                        autocomplete="name"
                        maxlength="150"
                    >
                    @error('display_name')
                        <div class="form-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address (Optional)</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        maxlength="255"
                    >
                    @error('email')
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
                        autocomplete="new-password"
                    >
                    @error('password')
                        <div class="form-error" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-control"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" id="setup-submit-button">
                        Initialize &amp; Create Administrator
                    </button>
                </div>
            </form>

            <div style="margin-top: 1.25rem; text-align: center;">
                <a href="{{ route('login') }}" style="font-size: var(--font-size-sm); color: var(--color-text-muted); text-decoration: none;">
                    &larr; Return to Sign In
                </a>
            </div>
        </div>
    </main>
</body>
</html>
