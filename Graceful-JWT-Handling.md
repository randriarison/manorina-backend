# Graceful JWT Token Handling Implementation

## Overview
Implemented graceful handling of expired and invalid JWT tokens for GET operations. Instead of returning 401 errors that break the user experience, expired/invalid tokens on GET requests are handled gracefully by treating them as anonymous requests.

## Problem Solved
- **Before**: Expired JWT tokens on GET requests returned 401 errors, breaking the user experience
- **After**: Expired JWT tokens on GET requests are gracefully handled, allowing access to public content

## Implementation Approach

### 1. JWT Preprocessor Event Listener
**File**: `src/EventListener/JWTPreprocessor.php`

**How it works**:
- Intercepts all requests before they reach the security layer
- For GET requests to `/api/*` endpoints with Authorization headers:
  - Validates the JWT token using `JWTEncoderInterface`
  - If token is valid: Allows normal authentication flow
  - If token is expired/invalid: Removes the Authorization header
- This causes the security system to treat invalid tokens as anonymous requests

**Key Features**:
```php
// Only processes GET requests to API endpoints
if (!$request->isMethod('GET') || !str_starts_with($request->getPathInfo(), '/api/')) {
    return;
}

try {
    $this->jwtEncoder->decode($token);
    // Token is valid - continue normally
} catch (\Exception $e) {
    // Token invalid - remove header for anonymous access
    $request->headers->remove('Authorization');
    $request->attributes->set('_jwt_removed_for_anonymous', true);
}
```

### 2. Service Configuration
**File**: `config/services.yaml`

```yaml
App\EventListener\JWTPreprocessor:
    arguments:
        $jwtEncoder: '@lexik_jwt_authentication.encoder'
        $logger: '@logger'
    tags:
        - { name: kernel.event_subscriber }
        - { name: monolog.logger, channel: security }
```

## Behavior by Request Type

### ✅ GET Requests (Graceful Handling)

| Scenario | Token Status | Result | HTTP Code |
|----------|-------------|---------|-----------|
| No token | N/A | Public content (published articles) | 200 |
| Valid token | ✅ Valid | Authenticated content (user articles + published) | 200 |
| Expired token | ❌ Expired | Public content (published articles) | 200 |
| Invalid token | ❌ Invalid | Public content (published articles) | 200 |

### ❌ POST/PUT/DELETE Requests (Secure Handling)

| Scenario | Token Status | Result | HTTP Code |
|----------|-------------|---------|-----------|
| No token | N/A | Unauthorized (if auth required) | 401 |
| Valid token | ✅ Valid | Normal operation | 200/201/204 |
| Expired token | ❌ Expired | Unauthorized | 401 |
| Invalid token | ❌ Invalid | Unauthorized | 401 |

## Security Considerations

### ✅ **Maintained Security**
- **Write Operations**: Still require valid authentication (POST/PUT/DELETE)
- **Authentication Required Endpoints**: Still properly protected
- **Valid Tokens**: Continue to work normally for all operations

### ✅ **Enhanced User Experience**
- **Expired Sessions**: Users can still browse content instead of being locked out
- **Gradual Degradation**: Seamless transition from authenticated to anonymous browsing
- **No Breaking Changes**: Existing functionality remains intact

### ✅ **Logging & Monitoring**
- All token validation attempts are logged
- Clear distinction between valid, expired, and invalid tokens
- Trackable anonymous access patterns

## Testing

### Test Command
```bash
php bin/console test:graceful-jwt
```

**Generated test scenarios**:
1. GET without token → Returns public content
2. GET with valid token → Returns authenticated content  
3. GET with expired token → Returns public content (graceful)
4. GET with invalid token → Returns public content (graceful)
5. POST with expired token → Returns 401 error (secure)

### Manual Testing Examples

**Graceful handling (GET requests)**:
```bash
# Should return published articles (no 401 error)
curl -X GET http://your-domain/api/articles \
  -H "Authorization: Bearer expired_token_here"
```

**Normal security (POST requests)**:
```bash
# Should return 401 error
curl -X POST http://your-domain/api/articles \
  -H "Authorization: Bearer expired_token_here" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","content":"Test"}'
```

## Performance Impact
- **Minimal overhead**: Only processes GET requests with Authorization headers
- **Early validation**: Token validation happens before security layer processing
- **Efficient processing**: Single JWT decode operation per request
- **No database impact**: No additional database queries

## Benefits

1. **🎯 Better UX**: Users can browse content even with expired sessions
2. **🔒 Maintained Security**: Write operations still require valid authentication
3. **📱 Mobile Friendly**: Handles app backgrounding/token expiration gracefully
4. **🔄 Seamless Transition**: Smooth degradation from authenticated to anonymous
5. **📊 Analytics Friendly**: Doesn't break user tracking with 401 errors

## Future Enhancements

Possible future improvements:
- Token refresh suggestions in response headers
- Configurable grace period for different token types
- Custom headers indicating graceful handling
- Integration with frontend token refresh mechanisms

The implementation provides a robust, secure, and user-friendly solution for handling expired JWT tokens while maintaining the security posture of the API.