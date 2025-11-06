<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Custom JWT Authenticator that handles expired tokens gracefully for GET requests
 * Instead of returning 401 errors for expired tokens, it allows the request to continue
 * as if no token was provided, enabling access to public content.
 */
class GracefulJWTAuthenticator extends JWTAuthenticator
{
    private ?LoggerInterface $logger = null;

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        // Only support if parent supports and we have a token
        return parent::supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            return parent::authenticate($request);
        } catch (ExpiredTokenException|InvalidTokenException $e) {
            // For GET requests, we want to handle expired/invalid tokens gracefully
            if ($request->isMethod('GET')) {
                $this->logger?->info('JWT authentication failed for GET request, will allow anonymous access', [
                    'uri' => $request->getRequestUri(),
                    'method' => $request->getMethod(),
                    'exception' => get_class($e)
                ]);
            }
            
            // Re-throw the exception - it will be caught by onAuthenticationFailure
            throw $e;
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // For GET requests with JWT failures, allow the request to continue anonymously
        if ($request->isMethod('GET') && 
            ($exception instanceof ExpiredTokenException || $exception instanceof InvalidTokenException)) {
            
            $this->logger?->debug('Gracefully handling JWT failure for GET request - allowing anonymous access', [
                'uri' => $request->getRequestUri(),
                'exception' => get_class($exception)
            ]);
            
            // Return null to skip authentication and continue the request
            return null;
        }

        // For non-GET requests or other exceptions, use normal JWT behavior
        return parent::onAuthenticationFailure($request, $exception);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue with the request after successful authentication
        return null;
    }
}