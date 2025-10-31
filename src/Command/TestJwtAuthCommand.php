<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-jwt-auth',
    description: 'Test JWT authentication in API documentation',
)]
class TestJwtAuthCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing JWT Authentication Configuration');

        $baseUrl = 'http://localhost:8000';
        
        // Test 1: Check OpenAPI documentation includes JWT security scheme
        $io->section('1. Checking OpenAPI Documentation');
        
        $openApiUrl = $baseUrl . '/api/docs.json';
        $openApiResponse = @file_get_contents($openApiUrl);
        
        if ($openApiResponse) {
            $openApiData = json_decode($openApiResponse, true);
            
            // Check if JWT security scheme is defined
            if (isset($openApiData['components']['securitySchemes']['JWT'])) {
                $jwtScheme = $openApiData['components']['securitySchemes']['JWT'];
                $io->success('✓ JWT security scheme found in OpenAPI documentation');
                $io->text('  - Type: ' . ($jwtScheme['type'] ?? 'N/A'));
                $io->text('  - Scheme: ' . ($jwtScheme['scheme'] ?? 'N/A'));
                $io->text('  - Bearer Format: ' . ($jwtScheme['bearerFormat'] ?? 'N/A'));
                $io->text('  - Description: ' . ($jwtScheme['description'] ?? 'N/A'));
            } else {
                $io->error('✗ JWT security scheme not found in OpenAPI documentation');
            }
            
            // Check if global security requirement is set
            if (isset($openApiData['security']) && is_array($openApiData['security'])) {
                $hasJwtSecurity = false;
                foreach ($openApiData['security'] as $securityReq) {
                    if (isset($securityReq['JWT'])) {
                        $hasJwtSecurity = true;
                        break;
                    }
                }
                
                if ($hasJwtSecurity) {
                    $io->success('✓ Global JWT security requirement configured');
                } else {
                    $io->warning('! Global JWT security requirement not found');
                }
            } else {
                $io->warning('! No global security requirements found');
            }
            
        } else {
            $io->error('✗ Could not fetch OpenAPI documentation');
        }

        // Test 2: Test login endpoint to get a valid token
        $io->section('2. Testing Login Endpoint');
        
        $loginUrl = $baseUrl . '/api/auth/login';
        $loginData = json_encode([
            'email' => 'test@example.com',
            'password' => 'password'
        ]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'content' => $loginData
            ]
        ]);
        
        $loginResponse = @file_get_contents($loginUrl, false, $context);
        
        if ($loginResponse) {
            $loginData = json_decode($loginResponse, true);
            if (isset($loginData['token'])) {
                $token = $loginData['token'];
                $io->success('✓ Login successful, JWT token obtained');
                $io->text('Token (first 50 chars): ' . substr($token, 0, 50) . '...');
                
                // Test 3: Test protected endpoint with JWT token
                $io->section('3. Testing Protected Endpoint with JWT');
                
                $protectedUrl = $baseUrl . '/api/articles';
                $postData = json_encode([
                    'title' => 'Test Article via JWT',
                    'content' => 'Testing JWT authentication',
                    'articleType' => 'simple',
                    'status' => 'published'
                ]);
                
                $authContext = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => [
                            'Content-Type: application/json',
                            'Accept: application/json',
                            'Authorization: Bearer ' . $token
                        ],
                        'content' => $postData
                    ]
                ]);
                
                $protectedResponse = @file_get_contents($protectedUrl, false, $authContext);
                
                if ($protectedResponse) {
                    $responseData = json_decode($protectedResponse, true);
                    if (isset($responseData['@id'])) {
                        $io->success('✓ Protected endpoint accessible with JWT token');
                        $io->text('Created article: ' . ($responseData['title'] ?? 'N/A'));
                    } else {
                        $io->warning('? Protected endpoint response format unexpected');
                    }
                } else {
                    $io->error('✗ Protected endpoint not accessible with JWT token');
                }
                
            } else {
                $io->error('✗ Login failed - no token in response');
            }
        } else {
            $io->warning('! Login endpoint not accessible (might need user creation first)');
        }

        $io->section('Instructions for API Documentation');
        $io->text([
            '1. Visit: http://localhost:8000/api',
            '2. Click on "Authorize" button (🔒 icon)',
            '3. Enter your JWT token with "Bearer " prefix',
            '4. Example: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...',
            '5. Click "Authorize" and then "Close"',
            '6. All subsequent API requests will include the token automatically',
            '',
            'The token should now be included in all API requests!'
        ]);

        $io->success('JWT authentication configuration test completed!');

        return Command::SUCCESS;
    }
}