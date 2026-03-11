<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification code</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 480px; margin: 0 auto; padding: 20px;">
    <p>Hello {{ $user->name }},</p>
    <p>Use the following code to complete your login:</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px; margin: 24px 0;">{{ $code }}</p>
    <p style="color: #666; font-size: 14px;">This code expires in 10 minutes. If you did not request this, you can safely ignore this email.</p>
</body>
</html>
