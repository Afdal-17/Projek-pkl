<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kode Verifikasi</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f6f7fb; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f6f7fb; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 420px; background-color: #ffffff; border-radius: 16px; border: 1px solid #e5e7eb; overflow: hidden;">
                    <tr>
                        <td style="padding: 32px 32px 8px 32px;">
                            <p style="margin: 0; font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #9ca3af;">{{ config('app.name', 'Finance App') }}</p>
                            <h1 style="margin: 12px 0 0 0; font-size: 22px; font-weight: 700; color: #111827;">Verifikasi Email Anda</h1>
                            <p style="margin: 12px 0 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
                                Halo <strong style="color: #374151;">{{ $namaPenerima }}</strong>, gunakan kode di bawah ini untuk memverifikasi email Anda saat pendaftaran.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 24px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                <tr>
                                    <td align="center" style="background-color: #f9fafb; border: 1px dashed #d1d5db; border-radius: 12px; padding: 18px 12px;">
                                        <span style="display: block; font-size: 32px; font-weight: 800; letter-spacing: 0.4em; color: #111827; padding-left: 0.4em;">{{ $kode }}</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 14px 0 0 0; font-size: 12px; color: #9ca3af;">
                                Berlaku selama <strong style="color: #6b7280;">{{ $menitBerlaku }} menit</strong>. Jangan bagikan kode ini kepada siapa pun.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 32px 32px 32px;">
                            <p style="margin: 0; font-size: 13px; line-height: 1.6; color: #6b7280;">
                                Jika Anda tidak merasa mendaftar di {{ config('app.name', 'Finance App') }}
                                , abaikan email ini. Setelah kode terverifikasi, akun Anda akan langsung aktif dan Anda masuk ke dashboard.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
