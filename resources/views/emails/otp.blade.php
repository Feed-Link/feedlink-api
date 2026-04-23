<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Your FeedLink OTP Code</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; display: block; }
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background-color: #f0f4f0;
            font-family: Georgia, 'Times New Roman', serif;
        }
    </style>
</head>
<body>
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f0f4f0; padding:40px 16px;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #c8ddc8;">

                    {{-- HEADER --}}
                    <tr>
                        <td align="center"
                            style="background: linear-gradient(135deg, #2d7a2d 0%, #4aaa2a 60%, #e0a020 100%);
                                   padding: 32px 32px 24px;">
                            <img src="{{ asset('images/feedlink-logo.png') }}"
                                 alt="FeedLink"
                                 width="64"
                                 style="margin-bottom:10px;" />
                            <p style="font-size:26px; font-weight:700; color:#ffffff;
                                      letter-spacing:-0.5px; margin:0; line-height:1;
                                      font-family:Georgia, serif;">
                                FeedLink
                            </p>
                            <p style="margin:8px 0 0; font-size:13px;
                                      color:rgba(255,255,255,0.85); font-style:italic;
                                      font-family:Georgia, serif;">
                                Connecting Surplus Food
                            </p>
                        </td>
                    </tr>

                    {{-- INTRO --}}
                    <tr>
                        <td align="center"
                            style="padding:32px 32px 20px; border-bottom:1px solid #e8f0e8;">

                            <div style="display:inline-block; background:#eaf3de;
                                        border-radius:50%; padding:14px; margin-bottom:16px;">
                                <svg width="32" height="32" viewBox="0 0 32 32"
                                     fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="8" width="24" height="18" rx="3"
                                          fill="none" stroke="#3B6D11" stroke-width="2"/>
                                    <path d="M4 12 L16 20 L28 12"
                                          stroke="#3B6D11" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="24" cy="8" r="5" fill="#4aaa2a"/>
                                    <path d="M21.5 8 L23 9.5 L26.5 6"
                                          stroke="white" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            <p style="font-size:15px; color:#2d7a2d; margin:0 0 4px;
                                      font-family:Georgia, serif;">
                                Hello, {{ $name ?? 'there' }} 👋
                            </p>
                            <h1 style="font-size:22px; font-weight:700; color:#1a4d1a;
                                       margin:0 0 10px; font-family:Georgia, serif;">
                                @if($purpose === 'reset')
                                    Reset your password
                                @else
                                    Verify your account
                                @endif
                            </h1>
                            <p style="font-size:14px; color:#5a7a5a; margin:0 0 0;
                                      line-height:1.7; font-family:Arial, sans-serif;">
                                @if($purpose === 'reset')
                                    Here is your one-time password to reset your
                                    <strong style="color:#2d7a2d;">FeedLink</strong> password.
                                @else
                                    Here is your one-time password to complete your
                                    <strong style="color:#2d7a2d;">FeedLink</strong> signup.
                                @endif
                                It expires in <strong style="color:#2d7a2d;">{{ config('one-time-passwords.default_expires_in_minutes') }} minutes</strong>.
                            </p>
                        </td>
                    </tr>

                    {{-- OTP --}}
                    <tr>
                        <td align="center" style="padding:28px 32px 20px;">

                            <p style="font-size:11px; font-weight:600; letter-spacing:2.5px;
                                      color:#7aaa4a; text-transform:uppercase; margin:0 0 14px;
                                      font-family:Arial, sans-serif;">
                                Your OTP code
                            </p>

                            <div style="display:inline-block; background:#f0f9e8;
                                        border:2px solid #97c459; border-radius:10px;
                                        padding:20px 36px; margin-bottom:20px;">
                                <p style="font-size:42px; font-weight:700; color:#1a4d1a;
                                          letter-spacing:14px; margin:0;
                                          font-family:'Courier New', Courier, monospace;">
                                    {{ $otp }}
                                </p>
                            </div>

                            <br />

                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:#fffbea; border:1px solid #e8c840;
                                               border-radius:8px; padding:10px 14px;">
                                        <table cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td valign="top" style="padding-right:8px;">
                                                    <svg width="16" height="16" viewBox="0 0 16 16"
                                                         fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <circle cx="8" cy="8" r="7" fill="#f0c030"/>
                                                        <text x="8" y="12" text-anchor="middle"
                                                              font-size="10" fill="white"
                                                              font-weight="700">!</text>
                                                    </svg>
                                                </td>
                                                <td>
                                                    <p style="font-size:12px; color:#7a6000;
                                                               margin:0; line-height:1.5;
                                                               font-family:Arial, sans-serif;">
                                                        If you did not request this, you can safely ignore this email.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- FOOTER --}}
                    <tr>
                        <td align="center"
                            style="background:#f5faf5; border-top:1px solid #c8ddc8;
                                   padding:20px 32px;">
                            <p style="font-size:12px; color:#7a9a7a; margin:0;
                                      font-family:Arial, sans-serif;">
                                &copy; {{ date('Y') }} FeedLink &mdash; All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
