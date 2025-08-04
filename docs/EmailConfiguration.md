# Email Configuration for Team Invitations

## Overview

The team invitation system uses Laravel's built-in mail functionality to send invitation emails to new team members. This document explains how to configure email settings for the team invitation feature.

## Email Configuration

### 1. Environment Variables

Add the following variables to your `.env` file:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 2. Gmail Configuration (Recommended for Development)

If using Gmail:

1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate an App Password**:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a password for "Mail"
3. **Use the app password** in `MAIL_PASSWORD`

### 3. Alternative Mail Services

#### Mailgun
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.com
MAILGUN_SECRET=your-mailgun-secret
```

#### SendGrid
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

#### Amazon SES
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
```

### 4. Testing Email Configuration

You can test the email configuration using Laravel's built-in mail testing:

```bash
# Test mail configuration
php artisan tinker
Mail::raw('Test email', function($message) { $message->to('test@example.com')->subject('Test'); });
```

## Team Invitation Email Features

### Email Template
- **Location**: `resources/views/emails/team-invitation.blade.php`
- **Mail Class**: `App\Mail\TeamInvitationMail`
- **Features**:
  - Professional HTML template
  - Company branding
  - Login credentials display
  - Security warnings
  - Responsive design

### Email Content
The invitation email includes:
- Welcome message with company name
- User's full name
- Login credentials (email and password)
- Security instructions
- Next steps for getting started

### Error Handling
- Email sending errors are logged
- Graceful failure handling
- No interruption to team invitation process

## Configuration Steps

### 1. Update .env File
```env
# Add your email configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="AssetGo"
```

### 2. Clear Configuration Cache
```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Test Email Sending
```bash
# Test with a sample invitation
php artisan tinker
$user = App\Models\User::first();
$password = 'test123';
Mail::to($user->email)->send(new App\Mail\TeamInvitationMail($user, $password));
```

## Troubleshooting

### Common Issues

1. **Authentication Failed**
   - Check username/password
   - Ensure 2FA is enabled for Gmail
   - Use app password, not regular password

2. **Connection Timeout**
   - Verify SMTP host and port
   - Check firewall settings
   - Try different encryption (tls/ssl)

3. **Email Not Sending**
   - Check Laravel logs: `storage/logs/laravel.log`
   - Verify mail configuration
   - Test with different mail service

### Debug Mode
Enable debug mode to see detailed error messages:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Security Considerations

1. **Password Security**
   - Generated passwords are random and secure
   - Users should change password after first login
   - Passwords are hashed before storage

2. **Email Security**
   - Use TLS encryption
   - Secure SMTP credentials
   - Monitor email sending logs

3. **Data Protection**
   - Email addresses are validated
   - Company-scoped invitations
   - Audit trail for all invitations

## Production Deployment

### Recommended Settings
```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your Company Name"
```

### Monitoring
- Monitor email sending success rates
- Set up email delivery tracking
- Configure email bounce handling
- Regular log review for errors

## Customization

### Email Template Customization
Edit `resources/views/emails/team-invitation.blade.php` to:
- Change styling and colors
- Add company logo
- Modify content and messaging
- Include additional information

### Mail Class Customization
Edit `app/Mail/TeamInvitationMail.php` to:
- Change email subject
- Add attachments
- Modify email headers
- Add custom logic

## Support

For email configuration issues:
1. Check Laravel documentation on mail configuration
2. Review mail service provider documentation
3. Check application logs for specific error messages
4. Test with different mail services if needed 