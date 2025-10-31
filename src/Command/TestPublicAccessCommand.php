<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-public-access',
    description: 'Test public access to Category and Article GET endpoints',
)]
class TestPublicAccessCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Public Access to API Endpoints');

        $baseUrl = 'http://localhost:8000/api';
        
        // Test endpoints that should be publicly accessible
        $publicEndpoints = [
            'GET /api/categories' => $baseUrl . '/categories',
            'GET /api/categories/{slug}' => $baseUrl . '/categories/technology',
            'GET /api/articles' => $baseUrl . '/articles', 
            'GET /api/articles/{slug}' => $baseUrl . '/articles/test-article-with-mixed-categories'
        ];

        $io->section('Public Endpoints (No Authentication Required)');

        foreach ($publicEndpoints as $description => $url) {
            $io->text("Testing: $description");
            $io->text("URL: $url");
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Accept: application/json',
                        'Content-Type: application/json'
                    ],
                    'timeout' => 10
                ]
            ]);

            $headers = get_headers($url);
            $statusCode = $headers[0] ?? 'Unknown';
            
            if (strpos($statusCode, '200') !== false) {
                $response = @file_get_contents($url, false, $context);
                if ($response !== false) {
                    $data = json_decode($response, true);
                    if ($data && isset($data['hydra:member'])) {
                        $io->success("✓ SUCCESS - Collection returned " . count($data['hydra:member']) . " items");
                    } elseif ($data && isset($data['@id'])) {
                        $io->success("✓ SUCCESS - Single item returned: " . ($data['name'] ?? $data['title'] ?? 'Item'));
                    } else {
                        $io->success("✓ SUCCESS - Valid response received");
                    }
                } else {
                    $io->success("✓ SUCCESS - Endpoint accessible (200 OK)");
                }
            } else {
                $io->error("✗ FAILED - $statusCode");
            }
            $io->newLine();
        }

        // Test endpoints that should require authentication
        $protectedEndpoints = [
            'POST /api/categories' => $baseUrl . '/categories',
            'POST /api/articles' => $baseUrl . '/articles'
        ];

        $io->section('Protected Endpoints (Authentication Required)');

        foreach ($protectedEndpoints as $description => $url) {
            $io->text("Testing: $description");
            $io->text("URL: $url");
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Accept: application/json',
                        'Content-Type: application/json'
                    ],
                    'content' => json_encode(['name' => 'Test']),
                    'timeout' => 10
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            $headers = get_headers($url);
            $statusCode = $headers[0] ?? 'Unknown';
            
            if (strpos($statusCode, '401') !== false) {
                $io->success("✓ CORRECT - Authentication required (401 Unauthorized)");
            } elseif (strpos($statusCode, '403') !== false) {
                $io->success("✓ CORRECT - Access forbidden (403 Forbidden)");
            } else {
                $io->warning("? Unexpected response: $statusCode");
            }
            $io->newLine();
        }

        $io->success('Public access configuration test completed!');
        $io->note('Categories and Articles GET operations should now be publicly accessible without authentication.');

        return Command::SUCCESS;
    }
}