# OAuth Redirect URI Environment Variable Implementation

## Summary
Replaced hardcoded `http://localhost:8080/auth/callback` URL in `OAuthRedirectController` with the environment variable `OAUTH_GOOGLE_REDIRECT_URI`.

## Changes Made

### 1. Updated OAuthRedirectController (`src/Controller/OAuthRedirectController.php`)

**Before:**
```php
class OAuthRedirectController extends AbstractController
{
    // ...
    
    public function handleRedirect(Request $request, string $provider): RedirectResponse
    {
        // ...
        $frontendUrl = 'http://localhost:8080/auth/callback?' . http_build_query($params);
        return new RedirectResponse($frontendUrl);
    }
}
```

**After:**
```php
class OAuthRedirectController extends AbstractController
{
    public function __construct(
        private string $oauthGoogleRedirectUri
    ) {
    }
    
    public function handleRedirect(Request $request, string $provider): RedirectResponse
    {
        // ...
        $frontendUrl = $this->oauthGoogleRedirectUri . '?' . http_build_query($params);
        return new RedirectResponse($frontendUrl);
    }
}
```

### 2. Updated Service Configuration (`config/services.yaml`)

Added service definition to inject the environment variable:
```yaml
# OAuth Redirect Controller with environment variable injection
App\Controller\OAuthRedirectController:
    arguments:
        $oauthGoogleRedirectUri: '%env(OAUTH_GOOGLE_REDIRECT_URI)%'
```

### 3. Environment Variable Configuration (`.env`)

The environment variable is already configured:
```properties
OAUTH_GOOGLE_REDIRECT_URI=http://localhost:8080/auth/callback
```

## Benefits

✅ **Flexibility**: Redirect URI can now be easily changed per environment without code modifications
✅ **Configuration**: Environment-based configuration follows Symfony best practices
✅ **Maintainability**: No more hardcoded URLs in the codebase
✅ **Deployment**: Different environments can use different redirect URIs

## Environment Variable Usage

- **Development**: `http://localhost:8080/auth/callback`
- **Production**: Can be set to production frontend URL
- **Testing**: Can be set to test environment URL

## Verification

Service container shows the environment variable is properly injected:
```bash
php bin/console debug:container App\Controller\OAuthRedirectController
```

Output confirms:
- Arguments: `%env(OAUTH_GOOGLE_REDIRECT_URI)%`
- Service properly configured and autowired

## Testing

Test the OAuth redirect functionality:
```bash
php bin/console test:oauth-redirect-uri
```

The redirect controller now dynamically uses the configured environment variable for all OAuth provider redirects, making the application more flexible and environment-aware.