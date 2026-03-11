<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 480px; margin: 0 auto; padding: 20px;">
    <p>Hello {{ $user->name }},</p>
    <p>We received a request to reset your password. Click the link below to choose a new password:</p>
    <p style="margin: 24px 0;">
        <a href="{{ $resetLink }}" style="display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500;">Reset password</a>
    </p>
    <p style="word-break: break-all; font-size: 12px; color: #666;">Or copy and paste this link into your browser:<br>{{ $resetLink }}</p>
    <p style="color: #666; font-size: 14px;">This link expires in 60 minutes. If you did not request a password reset, you can safely ignore this email.</p>
</body>
</html>
