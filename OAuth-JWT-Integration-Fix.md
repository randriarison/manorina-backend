# OAuth JWT Integration Fix

## Problem
The OAuth Google/Facebook authentication endpoints were returning OAuth2 access tokens instead of JWT tokens that the Symfony application could understand. This caused "Invalid JWT Token" errors when trying to use the returned tokens with protected API endpoints.

## Solution
Modified the OAuth controller to generate proper JWT tokens using Symfony's JWT Token Manager after successful OAuth authentication.

## Changes Made

### 1. Updated OAuthApiController.php
- **Added JWT Token Manager injection**: `JWTTokenManagerInterface $jwtManager`
- **Modified Google callback**: Now generates JWT token using `$this->jwtManager->create($user)`
- **Modified Facebook callback**: Same JWT token generation
- **Updated response format**: Changed from `access_token` to `token` containing JWT
- **Enhanced user creation**: Ensures OAuth users have proper roles (`ROLE_USER`)

### 2. Response Format Changes

**Before (OAuth2 token):**
```json
{
  "success": true,
  "user": { ... },
  "access_token": "oauth2_access_token_from_google",
  "expires_in": 3600,
  "provider": "google"
}
```

**After (JWT token):**
```json
{
  "success": true,
  "user": { ... },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "provider": "google",
  "google_access_token": "oauth2_token_for_reference",
  "google_expires_in": 3600
}
```

### 3. Usage Instructions

**Frontend Integration:**
1. Call `/api/oauth/google/callback` with the OAuth code
2. Extract the `token` field from the response (not `access_token`)
3. Use the JWT token in API calls: `Authorization: Bearer JWT_TOKEN_HERE`

**Example:**
```javascript
// After OAuth callback
const response = await fetch('/api/oauth/google/callback', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ code: authCode })
});

const data = await response.json();
const jwtToken = data.token; // Use this, not data.access_token

// Use JWT token for API calls
const articlesResponse = await fetch('/api/articles', {
  headers: { 'Authorization': `Bearer ${jwtToken}` }
});
```

## Testing
- ✅ JWT tokens are properly generated (verified with test command)
- ✅ JWT format is valid (3 parts, proper header/payload)
- ✅ Tokens work with protected API endpoints
- ✅ ArticleExtension now properly recognizes authenticated users

## Key Benefits
1. **Consistent Authentication**: All authentication methods now return JWT tokens
2. **Proper Security**: JWT tokens are signed and validated by Symfony
3. **User Context**: ArticleExtension and other security features work correctly
4. **API Consistency**: Same token format for both regular login and OAuth login

The OAuth authentication now properly integrates with your existing JWT-based API security system!