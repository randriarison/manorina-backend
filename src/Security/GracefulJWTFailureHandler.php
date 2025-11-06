<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Custom authentication failure handler for graceful JWT token handling
 * For GET requests, expired or invalid JWT tokens are handled gracefully
 * by allowing the request to continue without authentication instead of returning 401
 */
class GracefulJWTFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private AuthenticationFailureHandlerInterface $defaultHandler,
        private ?LoggerInterface $logger = null
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Check if this is a GET request with JWT token issues
        if ($request->isMethod('GET') && $this->isJWTTokenException($exception)) {
            $this->logger?->info('JWT authentication failed for GET request, allowing anonymous access', [
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'exception' => get_class($exception),
                'message' => $exception->getMessage()
            ]);

            // Create a special response that indicates the request should continue
            // We'll use a custom response that the firewall can recognize
            $response = new Response('', 200);
            $response->headers->set('X-JWT-Graceful-Failure', 'true');
            return $response;
        }

        // For non-GET requests or non-JWT exceptions, use the default handler
        return $this->defaultHandler->onAuthenticationFailure($request, $exception);
    }

    private function isJWTTokenException(AuthenticationException $exception): bool
    {
        return $exception instanceof ExpiredTokenException || 
               $exception instanceof InvalidTokenException ||
               $exception->getPrevious() instanceof ExpiredTokenException ||
               $exception->getPrevious() instanceof InvalidTokenException;
    }
}