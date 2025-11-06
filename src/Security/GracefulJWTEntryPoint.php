<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Custom authentication entry point for graceful JWT token handling
 * For GET requests, returns null to allow anonymous access instead of 401
 */
class GracefulJWTEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // For GET requests with JWT token issues, allow the request to continue
        if ($request->isMethod('GET') && $this->isJWTException($authException)) {
            $this->logger?->info('JWT authentication failed for GET request, allowing anonymous access', [
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'exception' => $authException ? get_class($authException) : 'none'
            ]);

            // Return a response that indicates the request should continue
            // We'll use a special response code that we can handle
            $response = new Response('', 204); // No Content
            $response->headers->set('X-JWT-Anonymous-Access', 'true');
            return $response;
        }

        // For non-GET requests, return standard 401
        return new Response('Unauthorized', 401, ['Content-Type' => 'application/json']);
    }

    private function isJWTException(?AuthenticationException $exception): bool
    {
        if (!$exception) {
            return false;
        }

        return $exception instanceof ExpiredTokenException ||
               $exception instanceof InvalidTokenException ||
               ($exception->getPrevious() && (
                   $exception->getPrevious() instanceof ExpiredTokenException ||
                   $exception->getPrevious() instanceof InvalidTokenException
               ));
    }
}