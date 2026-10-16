<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('club.welcome_subject') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif; color:#1f2d1f;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:100%; background-color:#ffffff; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#5cb85c; padding:28px 24px 0;">
                            <img src="{{ $heroImageUrl }}" alt="{{ config('carpoolear.name_app') }}" width="520" style="display:block; width:100%; max-width:520px; height:auto; border-radius:12px;">
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:28px 32px 0;">
                            <h1 style="margin:0; font-size:28px; line-height:1.15; text-transform:uppercase; letter-spacing:-0.02em; color:#3c5a3c;">
                                {{ __('club.hero_title_primary') }}<br>
                                <span style="color:#5cb85c;">{{ __('club.hero_title_accent') }}</span>
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 0;">
                            <p style="margin:0; font-size:16px; line-height:1.5; color:#1f2d1f; text-align:center;">
                                {{ __('club.intro') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 0;">
                            <ul style="margin:0; padding-left:20px; font-size:16px; line-height:1.5; color:#1f2d1f;">
                                <li style="margin-bottom:10px;"><strong>{{ __('club.benefit_priority_support_title') }}</strong> {{ __('club.benefit_priority_support_text') }}</li>
                                <li style="margin-bottom:10px;"><strong>{{ __('club.benefit_early_access_title') }}</strong> {{ __('club.benefit_early_access_text') }}</li>
                                <li style="margin-bottom:10px;"><strong>{{ __('club.benefit_semiannual_report_title') }}</strong> {{ __('club.benefit_semiannual_report_text') }}</li>
                                <li style="margin-bottom:0;"><strong>{{ __('club.benefit_badge_title') }}</strong> {{ __('club.benefit_badge_text') }}</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:32px 32px 8px;">
                            <a href="{{ $tripsUrl }}" style="display:inline-block; background-color:#5cb85c; color:#ffffff; text-decoration:none; font-size:16px; font-weight:bold; padding:14px 32px; border-radius:999px;">
                                {{ __('club.go_to_trips') }}
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px 32px;">
                            <p style="margin:0 0 4px; font-size:16px; line-height:1.5; color:#1f2d1f;">{{ __('club.signoff_greeting') }}</p>
                            <p style="margin:0; font-size:16px; line-height:1.5; color:#1f2d1f;">
                                <strong>{{ __('club.signoff_team_name') }}</strong> {{ __('club.signoff_team_note') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#f0f6f0; padding:16px 32px;">
                            <p style="margin:0; font-size:13px; color:#6b7a6b;">{{ config('carpoolear.name_app') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
