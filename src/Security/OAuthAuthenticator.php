<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OAuthAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $users,
        private EntityManagerInterface $em
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'oauth_check';
    }

    public function authenticate(Request $request): Passport
    {
        $service = $request->query->get('service') ?? 'google';
        $client = $this->clientRegistry->getClient($service);

        $accessToken = $client->getAccessToken();
        $resourceOwner = $client->fetchUserFromToken($accessToken);

        $email = $resourceOwner->getEmail();
        $providerId = $resourceOwner->getId();

        // Try existing user
        $user = match ($service) {
            'google' => $this->users->findOneBy(['googleId' => $providerId]),
            'facebook' => $this->users->findOneBy(['facebookId' => $providerId]),
            default => null,
        };

        // Try by email
        if (!$user && $email) {
            $user = $this->users->findOneBy(['email' => $email]);
        }

        // Create if not found
        if (!$user) {
            $user = new User();
            $user->setEmail($email);
        }

        // Link provider
        if ($service === 'google') {
            $user->setGoogleId($providerId);
        } elseif ($service === 'facebook') {
            $user->setFacebookId($providerId);
        }

        $this->em->persist($user);
        $this->em->flush();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn() => $user)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        return new RedirectResponse('/'); // redirect wherever you want
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        return new RedirectResponse('/login');
    }
}
