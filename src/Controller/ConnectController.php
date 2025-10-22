<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ConnectController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}
    /**
     * Get Google OAuth URL (for React/API usage)
     */
    #[Route('/api/auth/google/url', name: 'api_google_auth_url', methods: ['GET'])]
    public function getGoogleAuthUrl(ClientRegistry $clientRegistry): JsonResponse
    {
        $client = $clientRegistry->getClient('google');
        $authUrl = $client->getOAuth2Provider()->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile']
        ]);
        
        return new JsonResponse([
            'auth_url' => $authUrl,
            'provider' => 'google'
        ]);
    }

    /**
     * Get Facebook OAuth URL (for React/API usage)
     */
    #[Route('/api/auth/facebook/url', name: 'api_facebook_auth_url', methods: ['GET'])]
    public function getFacebookAuthUrl(ClientRegistry $clientRegistry): JsonResponse
    {
        $client = $clientRegistry->getClient('facebook');
        $authUrl = $client->getOAuth2Provider()->getAuthorizationUrl([
            'scope' => ['email', 'public_profile']
        ]);
        
        return new JsonResponse([
            'auth_url' => $authUrl,
            'provider' => 'facebook'
        ]);
    }

    /**
     * Get available OAuth providers
     */
    #[Route('/api/auth/providers', name: 'api_oauth_providers', methods: ['GET'])]
    public function getProviders(): JsonResponse
    {
        return new JsonResponse([
            'providers' => [
                [
                    'name' => 'Google',
                    'key' => 'google',
                    'api_url_endpoint' => $this->generateUrl('api_google_auth_url'),
                    'callback_endpoint' => $this->generateUrl('api_oauth_google_callback')
                ],
                [
                    'name' => 'Facebook', 
                    'key' => 'facebook',
                    'api_url_endpoint' => $this->generateUrl('api_facebook_auth_url'),
                    'callback_endpoint' => $this->generateUrl('api_oauth_facebook_callback')
                ]
            ]
        ]);
    }

    /**
     * Get user data by ID (for stateless authentication)
     */
    #[Route('/api/user/{id}', name: 'api_user_by_id', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getUserById(int $id): JsonResponse
    {
        $user = $this->em->getRepository(User::class)->find($id);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'providers' => [
                'google' => $user->getGoogleId() ? true : false,
                'facebook' => $user->getFacebookId() ? true : false,
            ]
        ]);
    }

    /**
     * Get current authenticated user information
     * For stateless mode, this endpoint returns user info if session exists
     * In production, you'd typically use JWT tokens or API keys
     */
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse([
                'error' => 'User not authenticated',
                'message' => 'No active session found. In stateless mode, use the user data from OAuth callback or authenticate with tokens.',
                'authenticated' => false
            ], 401);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            'providers' => [
                'google' => $user->getGoogleId() ? true : false,
                'facebook' => $user->getFacebookId() ? true : false,
            ],
            'authenticated' => true
        ]);
    }
}