{{-- Premium, brand-consistent email chrome.
     Required: $body_html_rendered (already merge-expanded HTML).
     Optional: $brand, $mail_footer.
     The shell intentionally holds NO content of its own beyond the logo header,
     accent bar, and footer — body templates are admin-editable and already
     include their own trip-detail tables and CTA buttons. Avoid duplicating
     that here, which previously caused two payment buttons + a wide overflow
     when a relation was accidentally stringified. --}}
@php
    // Q3: prefer owner_name (the clean brand) over mailer/mail_from name settings,
    // and never fall back to a client-specific demo brand.
    $brand        = $brand ?? (\ModelHelper::getDataFromSetting('owner_name') ?: \ModelHelper::getDataFromSetting('mailer_admin_name') ?: \ModelHelper::getDataFromSetting('mail_from_name') ?: 'our properties');
    $mail_footer  = $mail_footer ?? (string) \ModelHelper::getDataFromSetting('mail_footer');
    $accent       = '#d9a24a';
    $headerBg     = '#0b2820';
    $surface      = '#ffffff';
    $surfaceMuted = '#fafafb';
    $border       = '#e5e7eb';
    $textPrimary  = '#111827';
    $textMuted    = '#6b7280';

    // Q1: mirror the website header logo (header_logo) and build an ABSOLUTE URL with
    // asset()/url() (request-host aware, like the front-end + other mail templates) —
    // config('app.url') is localhost in dev so the logo never loaded in delivered mail.
    // (For cron-sent mail with no request, asset() falls back to APP_URL → set APP_URL
    //  to the real domain at go-live so images resolve everywhere.)
    $logoRel      = (string) (\ModelHelper::getDataFromSetting('header_logo') ?: \ModelHelper::getDataFromSetting('logo') ?: '');
    $logoUrl      = $logoRel ? asset(ltrim($logoRel, '/')) : null;
    $siteUrl      = rtrim(url('/'), '/');
    // Footer contact info uses the same canonical keys the website footer reads
    // (resources/views/front/layouts/footer.blade.php): `mobile` for phone and
    // `email` for the public mailto. `contact_us_receiving_mail` is the inbox
    // contact-form submissions are routed TO — not the public-facing address —
    // so it's only used as a fallback if no public email is set.
    $contactPhone = (string) \ModelHelper::getDataFromSetting('mobile');
    $contactEmail = (string) (\ModelHelper::getDataFromSetting('email')
                            ?: \ModelHelper::getDataFromSetting('contact_us_receiving_mail'));
    $address      = (string) \ModelHelper::getDataFromSetting('address');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand }}</title>
    <style>
        /* Force long unbroken strings (URLs, JSON-stringified objects, raw IDs)
           to wrap inside the body cell so the email never causes horizontal
           scroll on any client. Inline styles can't express word-break for
           descendants, so this scoped style block handles it. */
        .ca-mail-body, .ca-mail-body * { word-break: break-word; overflow-wrap: anywhere; }
        .ca-mail-body img { max-width: 100%; height: auto; }
        .ca-mail-body table { max-width: 100%; }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $surfaceMuted }};font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:{{ $textPrimary }};-webkit-font-smoothing:antialiased;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:{{ $surfaceMuted }};padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="max-width:640px;width:100%;background:{{ $surface }};border-radius:14px;overflow:hidden;border:1px solid {{ $border }};box-shadow:0 4px 12px rgba(15,23,42,0.04);">

            {{-- Brand header — logo + wordmark side-by-side. Logo on its own
                 looked too sparse, especially on transparent webp; the brand
                 name anchors the email visually and helps clients (Gmail
                 promo tab, Apple Mail dark mode) that strip / re-tint imagery. --}}
            <tr>
                <td style="background:{{ $headerBg }};padding:18px 28px;" align="left">
                    <a href="{{ $siteUrl }}" style="text-decoration:none;color:#ffffff;display:inline-block;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                @if($logoUrl)
                                    <td style="padding-right:12px;vertical-align:middle;">
                                        <img src="{{ $logoUrl }}" alt="{{ $brand }}" height="40" style="display:block;height:40px;width:auto;border:0;outline:none;text-decoration:none;">
                                    </td>
                                @endif
                                <td style="vertical-align:middle;color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.02em;line-height:1.1;">
                                    {{ $brand }}
                                </td>
                            </tr>
                        </table>
                    </a>
                </td>
            </tr>

            {{-- Accent bar --}}
            <tr><td style="height:4px;line-height:4px;font-size:0;background:{{ $accent }};">&nbsp;</td></tr>

            {{-- Body content (merge-expanded by caller) --}}
            <tr>
                <td class="ca-mail-body" style="padding:32px 32px 24px;font-size:15px;line-height:1.65;color:{{ $textPrimary }};">
                    {!! $body_html_rendered !!}
                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="padding:20px 32px 28px;border-top:1px solid {{ $border }};background:{{ $surfaceMuted }};">
                    @if(!empty($mail_footer))
                        <div style="color:{{ $textMuted }};font-size:12px;line-height:1.55;margin-bottom:12px;">{!! $mail_footer !!}</div>
                    @endif
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td style="font-size:12px;color:{{ $textMuted }};line-height:1.55;">
                                <strong style="color:{{ $textPrimary }};">{{ $brand }}</strong>
                                @if($address) &middot; {{ $address }} @endif
                                <br>
                                @if($contactEmail)<a href="mailto:{{ $contactEmail }}" style="color:{{ $textMuted }};text-decoration:none;">{{ $contactEmail }}</a>@endif
                                @if($contactEmail && $contactPhone) &middot; @endif
                                @if($contactPhone)<span>{{ $contactPhone }}</span>@endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="max-width:640px;width:100%;margin-top:14px;">
            <tr><td align="center" style="font-size:11px;color:{{ $textMuted }};line-height:1.5;padding:0 12px;">
                You are receiving this because you booked or enquired about a stay with {{ $brand }}.<br>
                &copy; {{ date('Y') }} {{ $brand }}. All rights reserved.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
