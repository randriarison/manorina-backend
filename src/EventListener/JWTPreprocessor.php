<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pre-processes requests to handle expired JWT tokens gracefully for GET requests
 * Removes invalid/expired JWT tokens from GET requests so they're treated as anonymous
 */
class JWTPreprocessor implements EventSubscriberInterface
{
    public function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private ?LoggerInterface $logger = null
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Process before the security firewall
            KernelEvents::REQUEST => ['onKernelRequest', 15],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Only process GET requests
        if (!$request->isMethod('GET')) {
            return;
        }

        // Only process API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Check for Authorization header
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return;
        }

        // Extract the token
        $token = substr($authHeader, 7);
        
        try {
            // Try to decode the token to check if it's valid
            $this->jwtEncoder->decode($token);
            
            // If we get here, the token is valid - let it continue normally
            $this->logger?->debug('JWT token is valid for GET request', [
                'uri' => $request->getRequestUri()
            ]);
            
        } catch (\Exception $e) {
            // Token is invalid or expired - remove it for GET requests
            $this->logger?->info('Removing invalid/expired JWT token for GET request to allow anonymous access', [
                'uri' => $request->getRequestUri(),
                'exception' => get_class($e),
                'message' => $e->getMessage()
            ]);
            
            // Remove the Authorization header so the request is treated as anonymous
            $request->headers->remove('Authorization');
            
            // Mark the request as having had a JWT failure for potential later use
            $request->attributes->set('_jwt_removed_for_anonymous', true);
        }
    }
}