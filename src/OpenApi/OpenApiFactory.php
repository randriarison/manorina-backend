<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model;

final class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        // Add JWT Authentication endpoints
        $paths->addPath('/api/auth/register', $this->createRegisterPath());
        $paths->addPath('/api/auth/login', $this->createLoginPath());
        $paths->addPath('/api/auth/verify-email', $this->createVerifyEmailPath());
        $paths->addPath('/api/auth/forgot-password', $this->createForgotPasswordPath());
        $paths->addPath('/api/auth/reset-password', $this->createResetPasswordPath());
        $paths->addPath('/api/auth/me', $this->createAuthMePath());

        // Add OAuth Social Login endpoints
        $paths->addPath('/api/oauth/google/url', $this->createGoogleUrlPath());
        $paths->addPath('/api/oauth/google/callback', $this->createGoogleCallbackPath());
        $paths->addPath('/api/oauth/facebook/url', $this->createFacebookUrlPath());
        $paths->addPath('/api/oauth/facebook/callback', $this->createFacebookCallbackPath());
        $paths->addPath('/api/me', $this->createLegacyMePath());

        // Add security schemes
        $components = $openApi->getComponents();
        $securitySchemes = $components?->getSecuritySchemes() ?: new \ArrayObject();
        $securitySchemes['JWT'] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'JWT token obtained from /api/auth/login endpoint'
        ]);

        // Set global security requirement for JWT
        $security = $openApi->getSecurity() ?: [];
        $security[] = new \ArrayObject(['JWT' => []]);

        return $openApi
            ->withComponents(
                $components?->withSecuritySchemes($securitySchemes) ?? 
                new Model\Components(securitySchemes: $securitySchemes)
            )
            ->withSecurity($security);
    }

    private function createRegisterPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'User Registration',
            post: new Model\Operation(
                operationId: 'register',
                tags: ['Authentication'],
                summary: 'Register a new user',
                description: 'Register with email validation and verification email',
                requestBody: new Model\RequestBody(
                    description: 'Registration data',
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'minLength' => 8, 'example' => 'StrongPass123!'],
                                    'name' => ['type' => 'string', 'example' => 'John Doe']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '201' => new Model\Response(description: 'User registered successfully'),
                    '400' => new Model\Response(description: 'Validation error'),
                    '409' => new Model\Response(description: 'User already exists')
                ]
            )
        );
    }

    private function createLoginPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'User Login',
            post: new Model\Operation(
                operationId: 'login',
                tags: ['Authentication'],
                summary: 'User login',
                description: 'Authenticate and get JWT token',
                requestBody: new Model\RequestBody(
                    description: 'Login credentials',
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email', 'password'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'example' => 'StrongPass123!']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Login successful, returns JWT token'),
                    '401' => new Model\Response(description: 'Invalid credentials'),
                    '403' => new Model\Response(description: 'Email not verified')
                ]
            )
        );
    }

    private function createVerifyEmailPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Email Verification',
            post: new Model\Operation(
                operationId: 'verifyEmail',
                tags: ['Authentication'],
                summary: 'Verify email address',
                description: 'Verify email with token from verification email',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['token'],
                                'properties' => [
                                    'token' => ['type' => 'string', 'example' => 'abc123def456...']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Email verified successfully'),
                    '400' => new Model\Response(description: 'Invalid or expired token')
                ]
            )
        );
    }

    private function createForgotPasswordPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Request Password Reset',
            post: new Model\Operation(
                operationId: 'forgotPassword',
                tags: ['Authentication'],
                summary: 'Request password reset',
                description: 'Send password reset email',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['email'],
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Password reset email sent (if account exists)')
                ]
            )
        );
    }

    private function createResetPasswordPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Reset Password',
            post: new Model\Operation(
                operationId: 'resetPassword',
                tags: ['Authentication'],
                summary: 'Reset password',
                description: 'Reset password with token from email',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['token', 'password'],
                                'properties' => [
                                    'token' => ['type' => 'string', 'example' => 'reset123token456...'],
                                    'password' => ['type' => 'string', 'minLength' => 8, 'example' => 'NewStrongPass123!']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Password reset successfully'),
                    '400' => new Model\Response(description: 'Invalid token or weak password')
                ]
            )
        );
    }

    private function createAuthMePath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Get Current User',
            get: new Model\Operation(
                operationId: 'getCurrentUser',
                tags: ['Authentication'],
                summary: 'Get current user info',
                description: 'Get authenticated user information (requires JWT)',
                security: [['JWT' => []]],
                responses: [
                    '200' => new Model\Response(description: 'Current user information'),
                    '401' => new Model\Response(description: 'Unauthorized - Invalid JWT token')
                ]
            )
        );
    }

    private function createGoogleUrlPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Google OAuth URL',
            get: new Model\Operation(
                operationId: 'getGoogleOAuthUrl',
                tags: ['OAuth Social Login'],
                summary: 'Get Google OAuth URL',
                description: 'Get Google OAuth authorization URL for social login',
                responses: [
                    '200' => new Model\Response(description: 'Google OAuth URL generated')
                ]
            )
        );
    }

    private function createGoogleCallbackPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Google OAuth Callback',
            post: new Model\Operation(
                operationId: 'googleOAuthCallback',
                tags: ['OAuth Social Login'],
                summary: 'Google OAuth callback',
                description: 'Exchange Google OAuth code for user data',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['code', 'state'],
                                'properties' => [
                                    'code' => ['type' => 'string', 'description' => 'Authorization code from Google'],
                                    'state' => ['type' => 'string', 'description' => 'State parameter for security']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'OAuth login successful'),
                    '400' => new Model\Response(description: 'Invalid code or state'),
                    '500' => new Model\Response(description: 'OAuth authentication failed')
                ]
            )
        );
    }

    private function createFacebookUrlPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Facebook OAuth URL',
            get: new Model\Operation(
                operationId: 'getFacebookOAuthUrl',
                tags: ['OAuth Social Login'],
                summary: 'Get Facebook OAuth URL',
                description: 'Get Facebook OAuth authorization URL for social login',
                responses: [
                    '200' => new Model\Response(description: 'Facebook OAuth URL generated')
                ]
            )
        );
    }

    private function createFacebookCallbackPath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Facebook OAuth Callback',
            post: new Model\Operation(
                operationId: 'facebookOAuthCallback',
                tags: ['OAuth Social Login'],
                summary: 'Facebook OAuth callback',
                description: 'Exchange Facebook OAuth code for user data',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => new Model\MediaType(
                            schema: new \ArrayObject([
                                'type' => 'object',
                                'required' => ['code', 'state'],
                                'properties' => [
                                    'code' => ['type' => 'string', 'description' => 'Authorization code from Facebook'],
                                    'state' => ['type' => 'string', 'description' => 'State parameter for security']
                                ]
                            ])
                        )
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'OAuth login successful'),
                    '400' => new Model\Response(description: 'Invalid code or state'),
                    '500' => new Model\Response(description: 'OAuth authentication failed')
                ]
            )
        );
    }

    private function createLegacyMePath(): Model\PathItem
    {
        return new Model\PathItem(
            summary: 'Legacy User Info (Stateless)',
            get: new Model\Operation(
                operationId: 'getLegacyUserInfo',
                tags: ['OAuth Social Login'],
                summary: 'Get user info (stateless mode)',
                description: 'Returns 401 with helpful message in stateless mode. Use OAuth callback data instead.',
                responses: [
                    '401' => new Model\Response(description: 'User not authenticated in stateless mode')
                ]
            )
        );
    }
}