{{--
    Login OTP email.

    Built with tables and inline styles rather than the site's design system:
    Outlook and most webmail clients strip <style> blocks, flexbox and grid,
    so anything structural has to survive on table layout alone.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login approval</title>
</head>
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1e293b;">

    {{-- Preheader: what shows in the inbox preview line, then hidden. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        {{ $otp->requesterName() }} is signing in. Code {{ $otp->code }}, valid {{ $otp->expiresInMinutes }} minutes.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f5f9; padding:24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e2e8f0;">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#1d4ed8; padding:24px 28px;">
                            <p style="margin:0; font-size:13px; font-weight:600; letter-spacing:0.06em; text-transform:uppercase; color:#bfdbfe;">
                                {{ $appName }}
                            </p>
                            <p style="margin:6px 0 0; font-size:20px; font-weight:700; color:#ffffff;">
                                Login approval request
                            </p>
                        </td>
                    </tr>

                    <!-- Who is asking -->
                    <tr>
                        <td style="padding:28px 28px 0;">
                            <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:#475569;">
                                Someone is trying to sign in to the admin panel. Share the code below
                                <strong>only if you recognise this request</strong>.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:13px; color:#64748b; width:34%;">Name</td>
                                    <td style="padding:14px 16px; font-size:14px; color:#0f172a; font-weight:600;">{{ $otp->requesterName() }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 16px 14px; font-size:13px; color:#64748b;">Email</td>
                                    <td style="padding:0 16px 14px; font-size:14px; color:#0f172a;">{{ $otp->user->email }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 16px 14px; font-size:13px; color:#64748b;">Time</td>
                                    <td style="padding:0 16px 14px; font-size:14px; color:#0f172a;">{{ $otp->requestedAt->format('d M Y, g:i A') }}</td>
                                </tr>
                                @if($otp->ipAddress)
                                <tr>
                                    <td style="padding:0 16px 14px; font-size:13px; color:#64748b;">IP address</td>
                                    <td style="padding:0 16px 14px; font-size:14px; color:#0f172a;">{{ $otp->ipAddress }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- The code -->
                    <tr>
                        <td style="padding:24px 28px 0;" align="center">
                            <p style="margin:0 0 10px; font-size:13px; color:#64748b; text-transform:uppercase; letter-spacing:0.06em; font-weight:600;">
                                One-time code
                            </p>
                            <p style="margin:0; font-size:38px; font-weight:700; letter-spacing:0.16em; color:#1d4ed8; font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;">
                                {{ $otp->code }}
                            </p>
                            <p style="margin:10px 0 0; font-size:13px; color:#94a3b8;">
                                Expires in {{ $otp->expiresInMinutes }} minutes
                            </p>
                        </td>
                    </tr>

                    <!-- Warning -->
                    <tr>
                        <td style="padding:24px 28px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:13px; line-height:1.6; color:#991b1b;">
                                        If you were not expecting this, <strong>do not share the code</strong>.
                                        Whoever requested it has this person's password &mdash; reset it from
                                        the admin panel.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                <p style="margin:18px 0 0; font-size:12px; color:#94a3b8; max-width:560px;">
                    Automated message from {{ $appName }}. Please do not reply.
                </p>

            </td>
        </tr>
    </table>

</body>
</html>
