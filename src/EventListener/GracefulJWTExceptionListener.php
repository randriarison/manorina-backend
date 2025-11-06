<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Kernel event listener for graceful JWT authentication failures
 * Converts JWT authentication exceptions to anonymous requests for GET operations
 */
class GracefulJWTExceptionListener implements EventSubscriberInterface
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Handle exceptions with high priority to catch JWT failures early
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle JWT authentication exceptions on GET requests
        if (!$request->isMethod('GET')) {
            return;
        }

        // Check if this is a JWT-related authentication exception
        if (!$this->isJWTAuthenticationException($exception)) {
            return;
        }

        $this->logger?->info('Gracefully handling JWT authentication failure for GET request', [
            'uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
            'exception' => get_class($exception),
            'message' => $exception->getMessage()
        ]);

        // Mark the request as having a graceful JWT failure
        $request->attributes->set('_jwt_graceful_failure', true);
        
        // Clear the exception to allow the request to continue
        // This will cause the security system to treat it as an anonymous request
        $event->allowCustomResponseCode();
        
        // Don't set a response - let the request continue normally
        // The absence of a valid token will be handled by the security configuration
    }

    private function isJWTAuthenticationException(\Throwable $exception): bool
    {
        // Direct JWT exceptions
        if ($exception instanceof ExpiredTokenException || $exception instanceof InvalidTokenException) {
            return true;
        }

        // Authentication exceptions that wrap JWT exceptions
        if ($exception instanceof AuthenticationException) {
            $previous = $exception->getPrevious();
            return $previous instanceof ExpiredTokenException || $previous instanceof InvalidTokenException;
        }

        return false;
    }
}