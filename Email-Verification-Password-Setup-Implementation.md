# Email Verification and Password Setup Implementation

## Overview
Implemented email verification flow where users can register and create their password via an email verification link.

## Implementation Date
April 21, 2026

## Features Implemented

### 1. User Registration with Email Verification
- Users can register via POST `/api/users` with just an email (password is optional)
- Upon registration, `isEmailVerified` is automatically set to `false`
- An `emailVerificationToken` is automatically generated in the User constructor
- A verification email is sent immediately after successful registration

### 2. Email Service Updates
**File**: `src/Service/EmailService.php`

- Updated `sendEmailVerification()` method to point to `/create-password` endpoint instead of `/verify-email`
- Email link format: `{FRONTEND_URL}/create-password?token={emailVerificationToken}`
- Frontend URL is configured in `.env` as `FRONTEND_URL`

### 3. Email Template Updates
**File**: `templates/emails/email_verification.html.twig`

- Updated email content to emphasize password creation
- Button text changed to "Create Password"
- Messaging updated to indicate that email verification happens automatically when password is created
- Still includes 24-hour expiration warning

### 4. UserProcessor Updates
**File**: `src/State/UserProcessor.php`

**Changes**:
- Added `EmailService` dependency injection
- Added logic to ensure `isEmailVerified = false` for new users during POST operations
- Sends verification email after successful user creation
- Email is only sent for POST operations (new users), not PATCH/PUT operations

### 5. New API Endpoint: Create Password
**File**: `src/Controller/AuthController.php`

**Endpoint**: `POST /api/auth/create-password`

**Request Body**:
```json
{
  "token": "verification_token_from_email",
  "password": "SecurePass123!"
}
```

**Functionality**:
- Validates the verification token
- Validates password strength (same rules as registration)
- Sets the user's password (hashed)
- Marks email as verified (`isEmailVerified = true`)
- Clears the verification token
- Automatically generates and returns a JWT token for immediate login

**Success Response** (200):
```json
{
  "success": true,
  "message": "Password created and email verified successfully",
  "token": "jwt_token_here",
  "user": {
    "id": "user-uuid",
    "email": "user@example.com",
    "name": "User Name",
    "email_verified": true,
    "email_verified_at": "2026-04-21 10:30:00",
    "roles": ["ROLE_USER"]
  }
}
```

**Error Responses**:
- 400: Missing fields, invalid token, already completed, weak password
- 500: Server error

### 6. User Entity Updates
**File**: `src/Entity/User.php`

- Added password field to `user:add` serialization group (in addition to `user:write`)
- This allows optional password during registration via API Platform
- Password remains nullable

## Registration Flow Options

### Option 1: Email-Only Registration (Recommended for this implementation)
1. User POSTs to `/api/users` with just `email` (and optionally `name`)
2. System creates user with `isEmailVerified = false` and generates token
3. Verification email is sent to user with link: `{FRONTEND_URL}/create-password?token={token}`
4. User clicks link and is taken to frontend password creation page
5. Frontend calls `POST /api/auth/create-password` with token and password
6. Backend verifies token, sets password, marks email as verified
7. User receives JWT token and is automatically logged in

### Option 2: Traditional Registration with Immediate Password
1. User POSTs to `/api/users` with `email`, `password`, and optionally `name`
2. System creates user with hashed password, `isEmailVerified = false`, generates token
3. Verification email is sent (though message mentions password creation)
4. User can still use the link to "reset" their password if desired
5. Or user can use existing `POST /api/auth/verify-email` endpoint if implemented

## Configuration

### Environment Variables (.env)
```env
# Email Configuration
FROM_EMAIL=noreply@manorina.com
FROM_NAME="Manorina Platform"

# Frontend URL for email verification links
# The email verification link will be: {FRONTEND_URL}/create-password?token={token}
# Users will create their password at this endpoint after registration
FRONTEND_URL=http://localhost:8080

# Mailer Configuration (already exists)
MAILER_DSN=smtp://your-email@gmail.com:your-app-password@smtp.gmail.com:587
```

## Frontend Requirements

The frontend needs to implement the following:

### 1. Password Creation Page
**Route**: `/create-password`

**Query Parameter**: `token` (from email link)

**UI Requirements**:
- Display email address (can fetch from token or ask user)
- Password input field with strength indicator
- Confirm password field
- Submit button

**Password Requirements** (display to user):
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

**API Call on Submit**:
```javascript
POST /api/auth/create-password
Content-Type: application/json

{
  "token": "token_from_url_query",
  "password": "user_entered_password"
}
```

**Success Handling**:
- Store JWT token from response
- Redirect to dashboard or home page
- Show success message

**Error Handling**:
- Display error message from API response
- For weak password errors, show specific requirements
- For invalid token, offer option to request new verification email

### 2. Registration Page (Optional Updates)
If you want email-only registration on frontend:
- Make password field optional
- Add messaging: "We'll send you an email to create your password"
- Or keep password field and let users choose

## Security Considerations

1. **Token Security**: 
   - Tokens are 64-character hex strings (256 bits of entropy)
   - Generated using `random_bytes(32)`
   - Stored in database, one-time use (cleared after verification)

2. **Password Requirements**:
   - Strong password validation enforced
   - Passwords are hashed using Symfony's password hasher
   - Minimum 8 characters with complexity requirements

3. **Email Verification**:
   - User cannot login until email is verified (enforced in login endpoint)
   - Token is cleared after successful verification

4. **Token Expiration**:
   - Email mentions 24-hour expiration
   - Consider implementing token expiration validation in future if needed
   - Users can request new verification email via `/api/auth/resend-verification`

## Testing

### Test Email-Only Registration Flow:
```bash
# 1. Create user with email only
POST /api/users
{
  "email": "test@example.com",
  "name": "Test User"
}

# 2. Check email for verification link (or check database for token)

# 3. Create password with token
POST /api/auth/create-password
{
  "token": "token_from_email_or_database",
  "password": "TestPass123!"
}

# 4. Verify you receive JWT token and user is logged in
```

### Test Traditional Registration Flow:
```bash
# 1. Create user with email and password
POST /api/users
{
  "email": "test2@example.com",
  "name": "Test User 2",
  "password": "TestPass123!"
}

# 2. Check that email is still sent
# 3. User can optionally use create-password endpoint to change password
```

## Database Schema

No migrations needed - all required fields already exist in User entity:
- `email` (required)
- `password` (nullable)
- `is_email_verified` (boolean, default false)
- `email_verification_token` (nullable string)
- `email_verified_at` (nullable datetime)

## Related Files Modified

1. `src/State/UserProcessor.php` - Added email sending logic
2. `src/Service/EmailService.php` - Updated verification URL
3. `templates/emails/email_verification.html.twig` - Updated email content
4. `src/Controller/AuthController.php` - Added create-password endpoint
5. `src/Entity/User.php` - Added password to user:add serialization group
6. `.env` - Added documentation for FRONTEND_URL

## Future Enhancements

1. **Token Expiration**: Add timestamp-based expiration (24 hours)
2. **Rate Limiting**: Limit password creation attempts per token
3. **Email Templates**: Consider separate templates for email-only vs traditional registration
4. **Notification**: Send welcome email after successful password creation
5. **Analytics**: Track registration completion rates
6. **Resend Link**: Add frontend UI for resending verification email

## Notes

- The existing `/api/auth/verify-email` endpoint still works for simple email verification
- The new `/api/auth/create-password` endpoint combines password setting + email verification
- Users with unverified emails cannot login (enforced in AuthController::login)
- The email service gracefully handles errors - registration succeeds even if email fails to send
