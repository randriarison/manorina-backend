<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AppAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em
    ) {}

    public function supports(Request $request): bool
    {
        $path = $request->getPathInfo();
        return str_starts_with($path, '/connect/') && str_contains($path, '/check');
    }
    public function authenticate(Request $request): Passport
    {
        $path = $request->getPathInfo();
        
        if (str_contains($path, '/google/check')) {
            try {
                $client = $this->clientRegistry->getClient('google');
                $accessToken = $client->getAccessToken();
                $googleUser = $client->fetchUserFromToken($accessToken);
                
                $userData = $googleUser->toArray();
                $email = $userData['email'] ?? null;
                if (!$email) {
                    throw new \LogicException('No email found in Google user data');
                }
                
                return new SelfValidatingPassport(
                    new UserBadge($email, fn() => $this->getOrCreateUserFromGoogle($googleUser))
                );
            } catch (\GuzzleHttp\Exception\ConnectException $e) {
                // Network connectivity issue - log and provide user-friendly error
                error_log("OAuth Network Error: " . $e->getMessage());
                throw new \Exception('Unable to connect to Google OAuth service. Please try again later.');
            } catch (\Exception $e) {
                error_log("OAuth Error: " . $e->getMessage());
                throw $e;
            }
        }

        if (str_contains($path, '/facebook/check')) {
            $client = $this->clientRegistry->getClient('facebook');
            $accessToken = $client->getAccessToken();
            $fbUser = $client->fetchUserFromToken($accessToken);
            
            $email = $fbUser->toArray()['email'] ?? null;
            if (!$email) {
                throw new \LogicException('No email found in Facebook user data');
            }
            
            return new SelfValidatingPassport(
                new UserBadge($email, fn() => $this->getOrCreateUserFromFacebook($fbUser))
            );
        }

        throw new \LogicException('Unsupported path: ' . $path);
    }

    private function getOrCreateUserFromGoogle($googleUser): User
    {
        $userData = $googleUser->toArray();
        $email = $userData['email'];
        $googleId = $googleUser->getId();
        
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

    private function getOrCreateUserFromFacebook($fbUser): User
    {
        $userData = $fbUser->toArray();
        $email = $userData['email'];
        $facebookId = $fbUser->getId();
        
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

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        // Get the authenticated user
        $user = $token->getUser();
        
        // Redirect to frontend with success and user info
        $frontendUrl = 'http://localhost:8080/auth?success=1&user_id=' . $user->getId();
        return new \Symfony\Component\HttpFoundation\RedirectResponse($frontendUrl);
    }

    public function onAuthenticationFailure(Request $request, \Throwable $exception): ?Response
    {
        return new Response('Authentication failed: '.$exception->getMessage(), Response::HTTP_UNAUTHORIZED);
    }
}
