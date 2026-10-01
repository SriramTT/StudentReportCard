<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Two-Step Verification - School Examination & Report Card Management System">
    <title>Two-Step Verification - School Examination & Report Card Management System</title>
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
                <p style="font-weight: 600; color: var(--color-primary, #1e3a8a); margin-top: 0.5rem;">
                    Two-Step Verification
                </p>
                <p style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-top: 0.25rem;">
                    A 6-digit code has been sent to your email <strong>{{ $maskedEmail }}</strong>. The code expires in 5 minutes.
                </p>
            </header>

            @if (session('status'))
                <div class="alert alert-success" role="status" style="margin-bottom: 1.25rem;">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" role="alert" style="margin-bottom: 1.25rem;">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.otp.verify') }}" novalidate id="otp-form">
                @csrf

                <div class="form-group">
                    <label for="otp" class="form-label" style="text-align: center; display: block;">Enter 6-Digit Verification Code</label>
                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        class="form-control @error('otp') is-invalid @enderror"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="••••••"
                        style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem; font-family: monospace; font-weight: 700;"
                    >
                    @error('otp')
                        <div class="form-error" role="alert" style="text-align: center; margin-top: 0.5rem;">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" id="otp-submit-button" style="width: 100%;">
                        Verify &amp; Sign In
                    </button>
                </div>
            </form>

            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 0.75rem; text-align: center;">
                <form method="POST" action="{{ route('login.otp.resend') }}" id="resend-form">
                    @csrf
                    <p style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted); margin-bottom: 0.5rem;">
                        Didn't receive the email? Check your spam folder or request a new code.
                    </p>
                    <button
                        type="submit"
                        id="resend-btn"
                        class="btn btn-secondary"
                        style="font-size: var(--font-size-sm); padding: 0.4rem 0.75rem;"
                        {{ ($cooldownRemaining ?? 0) > 0 ? 'disabled' : '' }}
                    >
                        <span id="resend-text">
                            @if(($cooldownRemaining ?? 0) > 0)
                                Resend Code (in {{ $cooldownRemaining }}s)
                            @else
                                Resend Code
                            @endif
                        </span>
                    </button>
                </form>

                <div style="margin-top: 0.5rem;">
                    <a href="{{ route('login') }}" style="font-size: var(--font-size-sm); color: var(--color-text-muted); text-decoration: none;">
                        &larr; Back to Sign In
                    </a>
                </div>
            </div>
        </div>
    </main>

    @if(($cooldownRemaining ?? 0) > 0)
    <script>
        (function() {
            var remaining = {{ (int) $cooldownRemaining }};
            var btn = document.getElementById('resend-btn');
            var text = document.getElementById('resend-text');

            var timer = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(timer);
                    btn.removeAttribute('disabled');
                    text.textContent = 'Resend Code';
                } else {
                    text.textContent = 'Resend Code (in ' + remaining + 's)';
                }
            }, 1000);
        })();
    </script>
    @endif
</body>
</html>
