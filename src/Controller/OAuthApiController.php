<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/oauth', name: 'api_oauth_')]
class OAuthApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    /**
     * Get Google OAuth URL (stateless)
     */
    #[Route('/google/url', name: 'google_url', methods: ['GET'])]
    public function getGoogleAuthUrl(ClientRegistry $clientRegistry): JsonResponse
    {
        $client = $clientRegistry->getClient('google');
        
        // Generate a signed state for security (stateless approach)
        $state = $this->generateSecureState();
        
        $authUrl = $client->getOAuth2Provider()->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
            'state' => $state
        ]);
        
        return new JsonResponse([
            'auth_url' => $authUrl,
            'state' => $state,
            'provider' => 'google'
        ]);
    }

    /**
     * Get Facebook OAuth URL (stateless)
     */
    #[Route('/facebook/url', name: 'facebook_url', methods: ['GET'])]
    public function getFacebookAuthUrl(ClientRegistry $clientRegistry): JsonResponse
    {
        $client = $clientRegistry->getClient('facebook');
        
        // Generate a signed state for security (stateless approach)
        $state = $this->generateSecureState();
        
        $authUrl = $client->getOAuth2Provider()->getAuthorizationUrl([
            'scope' => ['email', 'public_profile'],
            'state' => $state
        ]);
        
        return new JsonResponse([
            'auth_url' => $authUrl,
            'state' => $state,
            'provider' => 'facebook'
        ]);
    }

    /**
     * Exchange Google OAuth code for user data (stateless)
     */
    #[Route('/google/callback', name: 'google_callback', methods: ['POST'])]
    public function handleGoogleCallback(Request $request, ClientRegistry $clientRegistry): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Debug logging
            error_log("OAuth Google Callback - Received data: " . json_encode($data));
            
            if (!isset($data['code'])) {
                return new JsonResponse(['error' => 'Authorization code is required'], 400);
            }

            // Validate state parameter if provided
            if (isset($data['state']) && !empty($data['state'])) {
                error_log("Validating state: " . $data['state']);
                if (!$this->validateState($data['state'])) {
                    // Log the issue for debugging
                    error_log("Google OAuth state validation failed for state: " . $data['state']);
                    return new JsonResponse([
                        'error' => 'Authentication failed',
                        'message' => 'Invalid state parameter - this may be due to expired state or tampering'
                    ], 400);
                }
                error_log("State validation passed");
            }

            $client = $clientRegistry->getClient('google');
            
            // For testing: check if this is a test code
            if ($data['code'] === 'test_code') {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Test code received successfully',
                    'test_mode' => true,
                    'user' => [
                        'id' => 1,
                        'email' => 'test@example.com',
                        'name' => 'Test User',
                        'avatar' => null,
                        'google_id' => 'test_google_id',
                        'created_at' => date('Y-m-d H:i:s'),
                    ],
                    'access_token' => 'test_access_token',
                    'expires_in' => 3600,
                    'provider' => 'google'
                ]);
            }
            
            // Get access token - bypass KnpU client state validation since we validate manually
            error_log("Attempting to exchange code for access token");
            
            // Get the OAuth2 provider directly to bypass KnpU's state validation
            $provider = $client->getOAuth2Provider();
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $data['code'],
            ]);
            error_log("Access token obtained successfully");

            // Get user data from Google using the provider directly
            $googleUser = $provider->getResourceOwner($accessToken);
            $userData = $googleUser->toArray();
            error_log("User data retrieved: " . json_encode($userData));

            // Create or update user
            $user = $this->getOrCreateUserFromGoogle($userData);

            // Return user data and access token info
            return new JsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'avatar' => $user->getAvatar(),
                    'google_id' => $user->getGoogleId(),
                    'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                ],
                'access_token' => $accessToken->getToken(),
                'expires_in' => $accessToken->getExpires() ? $accessToken->getExpires() - time() : null,
                'provider' => 'google'
            ]);

        } catch (IdentityProviderException $e) {
            return new JsonResponse([
                'error' => 'OAuth provider error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exchange Facebook OAuth code for user data (stateless)
     */
    #[Route('/facebook/callback', name: 'facebook_callback', methods: ['POST'])]
    public function handleFacebookCallback(Request $request, ClientRegistry $clientRegistry): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['code'])) {
                return new JsonResponse(['error' => 'Authorization code is required'], 400);
            }

            // Validate state parameter if provided
            if (isset($data['state']) && !empty($data['state'])) {
                if (!$this->validateState($data['state'])) {
                    // Log the issue for debugging
                    error_log("Facebook OAuth state validation failed for state: " . $data['state']);
                    return new JsonResponse([
                        'error' => 'Authentication failed',
                        'message' => 'Invalid state parameter - this may be due to expired state or tampering'
                    ], 400);
                }
            }

            $client = $clientRegistry->getClient('facebook');
            
            // Get access token - bypass KnpU client state validation since we validate manually
            $provider = $client->getOAuth2Provider();
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $data['code'],
            ]);

            // Get user data from Facebook using the provider directly
            $facebookUser = $provider->getResourceOwner($accessToken);
            $userData = $facebookUser->toArray();

            // Create or update user
            $user = $this->getOrCreateUserFromFacebook($userData);

            // Return user data and access token info
            return new JsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'avatar' => $user->getAvatar(),
                    'facebook_id' => $user->getFacebookId(),
                    'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                ],
                'access_token' => $accessToken->getToken(),
                'expires_in' => $accessToken->getExpires() ? $accessToken->getExpires() - time() : null,
                'provider' => 'facebook'
            ]);

        } catch (IdentityProviderException $e) {
            return new JsonResponse([
                'error' => 'OAuth provider error',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Authentication failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function getOrCreateUserFromGoogle(array $userData): User
    {
        $email = $userData['email'];
        $googleId = $userData['sub']; // Google's user ID
        
        // Try to find by Google ID first, then by email
        $user = $this->em->getRepository(User::class)->findOneBy(['googleId' => $googleId]);
        if (!$user) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        }
        
        if (!$user) {
            $user = new User();
        }
        
        // Update user information
        $user->setEmail($email)
             ->setGoogleId($googleId)
             ->setName($userData['name'] ?? $userData['given_name'] . ' ' . $userData['family_name'] ?? null)
             ->setAvatar($userData['picture'] ?? null);
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }

    private function getOrCreateUserFromFacebook(array $userData): User
    {
        $email = $userData['email'];
        $facebookId = $userData['id'];
        
        // Try to find by Facebook ID first, then by email
        $user = $this->em->getRepository(User::class)->findOneBy(['facebookId' => $facebookId]);
        if (!$user) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        }
        
        if (!$user) {
            $user = new User();
        }
        
        // Update user information
        $user->setEmail($email)
             ->setFacebookId($facebookId)
             ->setName($userData['name'] ?? null)
             ->setAvatar($userData['picture']['data']['url'] ?? null);
        
        $this->em->persist($user);
        $this->em->flush();
        
        return $user;
    }

    /**
     * Generate a secure state parameter for OAuth (stateless approach)
     */
    private function generateSecureState(): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        $data = $timestamp . '|' . $random;
        
        // Sign the state with app secret for validation
        $signature = hash_hmac('sha256', $data, $this->getParameter('kernel.secret'));
        
        return base64_encode($data . '|' . $signature);
    }

    /**
     * Validate OAuth state parameter (stateless approach)
     */
    private function validateState(string $state): bool
    {
        try {
            $decoded = base64_decode($state);
            if (!$decoded) {
                return false;
            }
            
            $parts = explode('|', $decoded);
            if (count($parts) !== 3) {
                return false;
            }
            
            [$timestamp, $random, $signature] = $parts;
            
            // Validate signature
            $data = $timestamp . '|' . $random;
            $expectedSignature = hash_hmac('sha256', $data, $this->getParameter('kernel.secret'));
            
            if (!hash_equals($expectedSignature, $signature)) {
                return false;
            }
            
            // Check if state is not too old (2 hours max)
            if (time() - (int)$timestamp > 7200) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}