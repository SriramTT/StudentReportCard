<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Verification Code</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 560px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 32px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 20px;
            color: #1e3a8a;
            margin: 0;
        }
        .otp-box {
            background-color: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 18px;
            text-align: center;
            margin: 24px 0;
        }
        .otp-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 6px;
            color: #1e3a8a;
        }
        .footer {
            margin-top: 32px;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }
        .warning {
            font-size: 13px;
            color: #dc2626;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $schoolName ?: 'School Examination System' }}</h1>
            <p style="margin: 6px 0 0 0; font-size: 14px; color: #64748b;">Two-Step Verification</p>
        </div>

        <p>Hello,</p>
        <p>A sign-in request was received for your account. Please use the verification code below to complete your login:</p>

        <div class="otp-box">
            <div class="otp-code">{{ $otp }}</div>
        </div>

        <p>This code is valid for <strong>{{ $expiresInMinutes }} minutes</strong>. For security, do not share this code with anyone. School staff will never ask you for your verification code.</p>

        <p class="warning">
            If you did not initiate this sign-in attempt, please notify your school Administrator immediately.
        </p>

        <div class="footer">
            &copy; {{ date('Y') }} {{ $schoolName ?: 'School Examination & Report Card Management System' }}. All rights reserved.
        </div>
    </div>
</body>
</html>
