<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Passwort zurücksetzen</title>
    </head>
    <body style="margin:0;padding:0;background:#0b0b10;color:#e7e5e4;font-family:Georgia,serif;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b0b10;padding:24px 12px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#121217;border:1px solid #2a2a30;border-radius:14px;overflow:hidden;">
                        <tr>
                            <td style="padding:24px 28px;background:linear-gradient(180deg,#1a130f 0%,#121217 100%);border-bottom:1px solid #2a2a30;">
                                <p style="margin:0 0 8px 0;font-size:12px;letter-spacing:1.8px;text-transform:uppercase;color:#f59e0b;">C76-RPG</p>
                                <h1 style="margin:0;font-size:28px;line-height:1.2;color:#f5f5f4;font-family:'Trebuchet MS',sans-serif;">Passwort zurücksetzen</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 28px;">
                                <p style="margin:0 0 14px 0;font-size:16px;line-height:1.6;color:#d6d3d1;">Hallo {{ $userName }},</p>
                                <p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#d6d3d1;">Du hast eine Zurücksetzung für dein Passwort angefordert. Klicke auf den folgenden Button, um ein neues Passwort zu setzen.</p>

                                <p style="margin:24px 0;">
                                    <a
                                        href="{{ $resetUrl }}"
                                        style="display:inline-block;padding:12px 18px;border-radius:8px;border:1px solid #f59e0b;background:#7c2d12;color:#fef3c7;text-decoration:none;font-weight:700;letter-spacing:0.8px;text-transform:uppercase;font-size:12px;"
                                    >
                                        Passwort jetzt zurücksetzen
                                    </a>
                                </p>

                                <p style="margin:0 0 14px 0;font-size:14px;line-height:1.7;color:#a8a29e;">Der Link ist {{ $expirationMinutes }} Minuten gültig.</p>
                                <p style="margin:0 0 8px 0;font-size:14px;line-height:1.7;color:#a8a29e;">Falls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.</p>

                                <p style="margin:18px 0 0 0;font-size:12px;line-height:1.6;color:#78716c;word-break:break-all;">
                                    Direktlink: {{ $resetUrl }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:16px 28px;border-top:1px solid #2a2a30;color:#78716c;font-size:12px;letter-spacing:0.5px;">
                                {{ $appName }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
