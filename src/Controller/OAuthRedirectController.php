<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OAuthRedirectController extends AbstractController
{
    /**
     * Handle OAuth redirects from providers and forward to React frontend with the code
     */
    #[Route('/oauth/redirect/{provider}', name: 'oauth_redirect_handler', requirements: ['provider' => 'google|facebook'], methods: ['GET'])]
    public function handleRedirect(Request $request, string $provider): RedirectResponse
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');
        
        // Build query parameters for React frontend
        $params = [
            'provider' => $provider
        ];
        
        if ($code) {
            $params['code'] = $code;
        }
        
        if ($state) {
            $params['state'] = $state;
        }
        
        if ($error) {
            $params['error'] = $error;
            $errorDescription = $request->query->get('error_description');
            if ($errorDescription) {
                $params['error_description'] = $errorDescription;
            }
        }
        
        // Redirect to React frontend with the OAuth response parameters
        $frontendUrl = 'http://localhost:8080/auth/callback?' . http_build_query($params);
        
        return new RedirectResponse($frontendUrl);
    }
}