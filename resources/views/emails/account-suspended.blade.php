<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - {{ config('app.name') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .alert-icon {
            color: #dc3545;
            font-size: 48px;
            margin-bottom: 10px;
        }
        .title {
            color: #dc3545;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #dc3545;
            color: #856404;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .contact-info {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="alert-icon">⚠️</div>
            <div class="title">Account Suspended</div>
        </div>

        <p>Hello {{ $user->first_name }} {{ $user->last_name }},</p>

        <div class="warning-box">
            <strong>Important Notice:</strong> Your account has been suspended by an administrator. You will not be able to access the system until your account is reactivated.
        </div>

        <p>We wanted to inform you that your account access has been temporarily suspended. This action was taken by a system administrator.</p>

        <div class="info-box">
            <p><strong>What this means:</strong></p>
            <ul>
                <li>You are currently unable to log in to your account</li>
                <li>All active sessions have been terminated</li>
                <li>You will need to wait for your account to be reactivated by an administrator</li>
            </ul>
        </div>

        <p>If you believe this is an error, or if you need assistance with your account, please contact your administrator or support team.</p>

        @if($company)
        <div class="contact-info">
            <p><strong>Contact Information:</strong></p>
            <p>Company: {{ $company->name }}</p>
            @if($company->email)
            <p>Email: {{ $company->email }}</p>
            @endif
            @if($company->phone)
            <p>Phone: {{ $company->phone }}</p>
            @endif
        </div>
        @endif

        <p>Thank you for your understanding.</p>

        <div class="footer">
            <p>This is an automated message from {{ config('app.name') }}.</p>
            <p>If you have any questions about this suspension, please contact your administrator immediately.</p>
        </div>
    </div>
</body>
</html>

