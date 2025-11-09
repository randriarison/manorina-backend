# Updated CreateAdminUserCommand - Batch User Creation

## Overview
The `CreateAdminUserCommand` has been updated to handle multiple admin users from a predefined array. The command intelligently checks for existing users and only creates those that don't already exist in the database.

## Features

✅ **Batch Processing**: Create multiple admin users in one command execution  
✅ **Duplicate Prevention**: Automatically skips users that already exist  
✅ **Error Handling**: Gracefully handles individual user creation failures  
✅ **Detailed Reporting**: Shows comprehensive summary of the operation  
✅ **Safe Operation**: Uses database transactions for data integrity  

## Usage

### Running the Command
```bash
php bin/console app:create-admin-user
```

### Adding New Users
To add new admin users, simply update the `$usersToCreate` array in the command file:

```php
$usersToCreate = [
    [
        'email' => 'onyvoni@gmail.com',
        'password' => 'ony@man0r1n@',
        'name' => 'ony'
    ],
    [
        'email' => 'admin@manorina.com',
        'password' => 'Admin123!',
        'name' => 'Main Admin'
    ],
    [
        'email' => 'moderator@manorina.com',
        'password' => 'Mod456!',
        'name' => 'Content Moderator'
    ],
    // Add more users here:
    [
        'email' => 'newadmin@example.com',
        'password' => 'SecurePassword!',
        'name' => 'New Admin'
    ],
];
```

## Command Output

The command provides detailed feedback during execution:

### Processing Phase
```
Processing 3 user(s)...

Checking user: onyvoni@gmail.com
  ➤ User already exists, skipping...
Checking user: admin@manorina.com
  ✅ User prepared for creation
Checking user: moderator@manorina.com
  ✅ User prepared for creation
```

### Success Summary
```
Successfully created 2 admin user(s)!

Created Admin Users
-------------------
Email                    Name                Roles                   Password    Created At
admin@manorina.com       Main Admin          ROLE_ADMIN, ROLE_USER   Admin123!   2025-11-09 18:34:15
moderator@manorina.com   Content Moderator   ROLE_ADMIN, ROLE_USER   Mod456!     2025-11-09 18:34:16
```

### Final Summary
```
Summary
-------
Total Processed              3
Successfully Created         2
Already Existing (Skipped)   1
Failed                       0

Existing Users (Skipped)
------------------------
• onyvoni@gmail.com (ony)
```

## User Properties

Each user in the array must have these properties:

| Property   | Type   | Description                    | Example                  |
|------------|--------|--------------------------------|--------------------------|
| `email`    | string | Unique email address (login)   | `admin@manorina.com`     |
| `password` | string | Plain text password (hashed)   | `SecurePassword123!`     |
| `name`     | string | Display name                   | `Main Administrator`     |

## Automatic Features

### User Configuration
- **Role**: All created users get `ROLE_ADMIN` by default
- **Email Verification**: Users are automatically marked as email verified
- **Password Hashing**: Plain text passwords are securely hashed
- **Timestamps**: Created/updated timestamps are automatically set

### Safety Features
- **Duplicate Check**: Email uniqueness is enforced
- **Transaction Safety**: All users are created in a single database transaction
- **Error Isolation**: Individual user failures don't affect others
- **Rollback Protection**: Failed operations don't leave partial data

## Error Handling

The command handles various error scenarios:

1. **Existing Users**: Skipped with notification
2. **Database Errors**: Reported with detailed error messages
3. **Validation Failures**: Individual user failures are logged
4. **Transaction Failures**: Complete rollback with error details

## Best Practices

### Password Security
- Use strong passwords with mixed characters
- Consider using environment variables for sensitive passwords
- Encourage password changes after first login

### Email Configuration
- Use valid email addresses for password reset functionality
- Consider using role-specific email addresses (admin@, support@, etc.)
- Ensure email domains are properly configured

### Deployment
```bash
# Production deployment
php bin/console app:create-admin-user --env=prod

# Clear cache after user creation
php bin/console cache:clear --env=prod
```

## File Location
`src/Command/CreateAdminUserCommand.php`

## Integration
The command integrates seamlessly with:
- Symfony Security system
- Doctrine ORM user management
- Password hashing services
- Email verification workflow

This updated command provides a robust, scalable solution for managing admin users across different environments while maintaining security and data integrity.