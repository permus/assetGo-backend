# Team Management System Documentation

## Overview

This document describes the simplified team management system implemented for the AssetGo backend. The system manages team members as users with `user_type = 'team'`, allowing companies to invite team members with email invitations and role-based permissions.

## Database Structure

### Users Table (Extended)
Team members are stored in the existing `users` table with:
- `user_type` = 'team' (to distinguish from company owners)
- `company_id` (links to the company they belong to)
- `created_by` (who invited them)
- Standard user fields (name, email, password, etc.)

### Relationships
- **Company** has many **Users** (where user_type = 'team')
- **User** belongs to **Company**
- **User** has many **Roles** (for permissions)

## Models

### User Model (Updated)
Team members are regular users with `user_type = 'team'`

**Key Methods for Team Management:**
- `roles()` - Get assigned roles for permissions
- `hasPermission($module, $action)` - Check permissions
- `getAllPermissions()` - Get all permissions through roles

## API Endpoints

### Team Management

#### Get All Team Members
```
GET /api/teams
```
Returns all team members (users with `user_type = 'team'`) for the authenticated user's company.

#### Invite Team Member
```
POST /api/teams
```
**Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john.doe@example.com",
  "role_id": 1,
  "hourly_rate": 25.50
}
```

**Response:**
- Creates a new user with `user_type` set to "team"
- Assigns the specified role to the user
- Generates random password
- Sends invitation email with login credentials
- Returns team member data

#### Get Team Member Details
```
GET /api/teams/{id}
```

#### Update Team Member
```
PUT /api/teams/{id}
```
**Body:**
```json
{
  "first_name": "Updated Name",
  "last_name": "Updated Last",
  "email": "updated@example.com",
  "role_id": 2
}
```

#### Remove Team Member
```
DELETE /api/teams/{id}
```

#### Resend Invitation
```
POST /api/teams/{id}/resend-invitation
```
Generates a new password and sends invitation email.

#### Get Team Statistics
```
GET /api/teams/statistics
```
Returns:
- Total team members
- Active team members (email verified)
- Pending team members (email not verified)

#### Get Available Roles
```
GET /api/teams/available-roles
```
Returns all available roles for the company.

## Team Invitation Process

### When Inviting a Team Member:

1. **User Creation:**
   - Creates a new user with `user_type` = "team"
   - Sets company_id to the inviting user's company
   - Generates a random password

2. **Role Assignment:**
   - Assigns the specified role by role_id to the new user
   - Role determines the user's permissions
   - Validates that the role belongs to the company

3. **Email Notification:**
   - Sends professional invitation email with login credentials
   - Uses Laravel Mail system with custom template
   - Includes company branding and security instructions

4. **Permission System:**
   - Team users get permissions based on their assigned roles
   - Company users (owners) get full permissions

## Login Response with Permissions

### For Team Users:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "user_type": "team",
      "company": {...},
      "roles": [...]
    },
    "token": "1|abc123...",
    "token_type": "Bearer",
    "email_verified": true,
    "permissions": {
      "assets": {
        "can_view": true,
        "can_create": false,
        "can_edit": true,
        "can_delete": false,
        "can_export": false
      },
      "locations": {
        "can_view": true,
        "can_create": false,
        "can_edit": false,
        "can_delete": false,
        "can_export": false
      }
    }
  }
}
```

### For Company Users (Owners):
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {...},
    "token": "1|abc123...",
    "token_type": "Bearer",
    "email_verified": true,
    "permissions": {
      "assets": {
        "can_view": true,
        "can_create": true,
        "can_edit": true,
        "can_delete": true,
        "can_export": true
      },
      "locations": {
        "can_view": true,
        "can_create": true,
        "can_edit": true,
        "can_delete": true,
        "can_export": true
      }
    }
  }
}
```

## System Roles (determines permissions):
- **User** - Basic access (view assets and locations only)
- **Technician** - Enhanced access (view and edit assets, work orders, maintenance)
- **Admin** - Full access (all permissions)

## Auto-Created Roles
When a company registers, the following roles are automatically created:
1. **Admin** - Full permissions for all modules
2. **Technician** - Enhanced permissions for assets, work orders, maintenance
3. **User** - Basic permissions for viewing assets and locations

## Usage Examples

### Inviting a Team Member
```php
// This is handled by the API endpoint
// Creates user with user_type = 'team', assigns role, sends email
```

### Getting Team Members
```php
$teamMembers = $company->users()->where('user_type', 'team')->get();
```

### Checking if User is Team Member
```php
if ($user->user_type === 'team') {
    // User is a team member
}
```

### Getting Team Member Permissions
```php
$permissions = $teamMember->getAllPermissions();
```

## API Examples

### Invite Team Member
```bash
curl -X POST /api/teams \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "role_id": 1,
    "hourly_rate": 25.50
  }'
```

### Get All Team Members
```bash
curl -X GET /api/teams \
  -H "Authorization: Bearer {token}"
```

### Update Team Member
```bash
curl -X PUT /api/teams/1 \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "Updated Name",
    "role_id": 2
  }'
```

### Resend Invitation
```bash
curl -X POST /api/teams/1/resend-invitation \
  -H "Authorization: Bearer {token}"
```

### Get Available Roles
```bash
curl -X GET /api/teams/available-roles \
  -H "Authorization: Bearer {token}"
```

## Email Configuration

The team invitation system sends professional HTML emails to new team members. To configure email sending:

### Required Environment Variables
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="AssetGo"
```

### Email Features
- **Professional HTML Template** with company branding
- **Login Credentials** clearly displayed
- **Security Instructions** for password protection
- **Responsive Design** for mobile devices
- **Error Handling** with logging

For detailed email configuration instructions, see `docs/EmailConfiguration.md`.

## Security Considerations

1. **Company Isolation:** Team members are scoped to companies
2. **User Type Validation:** Team users have restricted permissions
3. **Role-based Access:** Permissions are determined by assigned roles
4. **Email Verification:** Team members must verify their email
5. **Secure Password Generation:** Random passwords for team invitations
6. **Token Management:** Proper token handling for authentication
7. **Email Security:** TLS encryption for email transmission

## Best Practices

1. **Always validate team member ownership** before performing operations
2. **Use the invitation system** for adding team members
3. **Assign appropriate roles** based on user responsibilities
4. **Monitor team member status** (active/pending)
5. **Implement proper email notifications** for invitations
6. **Regularly audit team member permissions**
7. **Use soft deletes** for team members
8. **Log team member activities** for audit trails

## Integration with Permission System

The team system integrates with the role and permission system:

1. **Team users** get permissions based on their assigned roles
2. **Company users** get full permissions by default
3. **Login response** includes user permissions
4. **Middleware protection** can be applied to team-specific routes
5. **Permission checking** works for both team and company users

## Future Enhancements

1. **Email Templates:** ✅ Implemented - Professional HTML email template with company branding
2. **Team Analytics:** Add team performance and activity tracking
3. **Work Order Assignment:** Assign work orders to team members
4. **Time Tracking:** Integrate with hourly rate tracking
5. **Team Reports:** Generate team-specific reports
6. **Team Notifications:** Add team-specific notification system
7. **Team Chat:** Integrate team communication features
8. **File Sharing:** Add team-specific file sharing capabilities 