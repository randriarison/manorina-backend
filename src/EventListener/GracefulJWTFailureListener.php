<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event listener for graceful JWT authentication failures on GET requests
 * Instead of returning 401 for expired/invalid tokens on GET requests,
 * it allows the request to continue as if no token was provided
 */
class GracefulJWTFailureListener implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private ?LoggerInterface $logger = null
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
        ];
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return;
        }

        $exception = $event->getException();
        
        // Only handle GET requests with JWT token issues gracefully
        if ($request->isMethod('GET') && $this->isGracefulException($exception)) {
            $this->logger?->info('JWT authentication failed for GET request, allowing anonymous access', [
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'exception' => get_class($exception),
                'message' => $exception->getMessage()
            ]);

            // Mark the request to be handled anonymously
            $request->attributes->set('_jwt_graceful_failure', true);
            
            // Prevent the default JWT failure response
            $event->setResponse(null);
        }
    }

    private function isGracefulException(\Throwable $exception): bool
    {
        return $exception instanceof ExpiredTokenException ||
               $exception instanceof InvalidTokenException ||
               ($exception->getPrevious() && (
                   $exception->getPrevious() instanceof ExpiredTokenException ||
                   $exception->getPrevious() instanceof InvalidTokenException
               ));
    }
}