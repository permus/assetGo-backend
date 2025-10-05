<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation - {{ $company->name }}</title>
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
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            color: #007bff;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .welcome-text {
            font-size: 18px;
            color: #333;
        }
        .credentials-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .credential-item {
            margin: 10px 0;
            padding: 10px;
            background-color: #ffffff;
            border-radius: 3px;
            border-left: 4px solid #007bff;
        }
        .label {
            font-weight: bold;
            color: #495057;
            display: inline-block;
            width: 100px;
        }
        .value {
            color: #007bff;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
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
        .button {
            display: inline-block;
            background-color: #007bff;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-name">{{ $company->name }}</div>
            <div class="welcome-text">Welcome to the Team!</div>
        </div>

        <p>Hello {{ $user->first_name }} {{ $user->last_name }},</p>

        <p>You have been invited to join <strong>{{ $company->name }}</strong> as a team member. We're excited to have you on board!</p>

        @if($isCustomPassword)
            <p>Your account has been created with the password you provided. Below are your login credentials to access the AssetGo platform:</p>

            <div class="credentials-box">
                <div class="credential-item">
                    <span class="label">Email:</span>
                    <span class="value">{{ $user->email }}</span>
                </div>
                <div class="credential-item">
                    <span class="label">Password:</span>
                    <span class="value">{{ $password }}</span>
                </div>
            </div>

            <div class="warning">
                <strong>Important:</strong> Please keep your login credentials secure and do not share them with anyone. You can change your password after your first login.
            </div>
        @else
            <p>Below are your login credentials to access the AssetGo platform:</p>

            <div class="credentials-box">
                <div class="credential-item">
                    <span class="label">Email:</span>
                    <span class="value">{{ $user->email }}</span>
                </div>
                <div class="credential-item">
                    <span class="label">Password:</span>
                    <span class="value">{{ $password }}</span>
                </div>
            </div>

            <div class="warning">
                <strong>Important:</strong> Please keep your login credentials secure and do not share them with anyone. You can change your password after your first login.
            </div>
        @endif

        <p>To get started:</p>
        <ol>
            <li>Visit the AssetGo platform</li>
            <li>Use the credentials above to log in</li>
            <li>Complete your profile setup</li>
            <li>Start collaborating with your team!</li>
        </ol>

        <p>If you have any questions or need assistance, please don't hesitate to contact your team administrator.</p>

        <p>Welcome aboard!</p>

        <div class="footer">
            <p>This is an automated message from {{ $company->name }} via AssetGo platform.</p>
            <p>If you did not expect this invitation, please contact your administrator immediately.</p>
        </div>
    </div>
</body>
</html> 