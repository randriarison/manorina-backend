<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OAuthController extends AbstractController
{
    #[Route('/connect/{service}', name: 'oauth_redirect')]
    public function connect(string $service, ClientRegistry $clientRegistry): Response
    {
        $scopes = $service === 'google' ? ['openid', 'email', 'profile'] : ['email'];
        return $clientRegistry->getClient($service)->redirect($scopes, []);
    }

    #[Route('/connect/check', name: 'oauth_check')]
    public function check(): Response
    {
        // handled by authenticator automatically
        return $this->redirect('/');
    }
}
