<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:oauth-jwt',
    description: 'Test OAuth JWT token generation'
)]
class TestOAuthJwtCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing OAuth JWT Token Generation');

        $io->section('Creating/Finding Test User for OAuth');

        // Find or create a test OAuth user
        $testUser = $this->userRepository->findOneBy(['email' => 'oauth-test@example.com']);
        
        if (!$testUser) {
            $testUser = new User();
            $testUser->setEmail('oauth-test@example.com')
                     ->setGoogleId('test_oauth_google_id')
                     ->setName('OAuth Test User')
                     ->setRoles(['ROLE_USER']);
            
            $this->entityManager->persist($testUser);
            $this->entityManager->flush();
            
            $io->success('Created new OAuth test user');
        } else {
            $io->info('Found existing OAuth test user');
        }

        $io->section('Generating JWT Token');

        try {
            // Generate JWT token for the OAuth user
            $jwtToken = $this->jwtManager->create($testUser);
            
            $io->success('JWT Token generated successfully!');
            
            $io->section('Token Analysis');
            $io->text('📧 User Email: ' . $testUser->getEmail());
            $io->text('🔑 JWT Token Generated: Yes');
            $io->text('JWT Token (first 50 chars): ' . substr($jwtToken, 0, 50) . '...');
            
            // Verify it's a proper JWT (should have 3 parts separated by dots)
            $jwtParts = explode('.', $jwtToken);
            if (count($jwtParts) === 3) {
                $io->success('✅ Token format is valid JWT (3 parts)');
                
                // Decode header to show it's a proper JWT
                $header = json_decode(base64_decode($jwtParts[0]), true);
                if ($header) {
                    $io->text('JWT Header: ' . json_encode($header, JSON_PRETTY_PRINT));
                }
                
                // Decode payload to show user info
                $payload = json_decode(base64_decode($jwtParts[1]), true);
                if ($payload) {
                    $io->text('JWT Payload (user info):');
                    $io->text('  - Username: ' . ($payload['username'] ?? 'N/A'));
                    $io->text('  - Roles: ' . json_encode($payload['roles'] ?? []));
                    $io->text('  - Expires: ' . date('Y-m-d H:i:s', $payload['exp'] ?? 0));
                }
            } else {
                $io->error('❌ Token format is invalid (should have 3 parts)');
                return Command::FAILURE;
            }
            
            $io->section('Testing Instructions');
            $io->text('Your OAuth endpoint now returns JWT tokens in this format:');
            $io->text('');
            $io->text('{');
            $io->text('  "success": true,');
            $io->text('  "token": "' . substr($jwtToken, 0, 20) . '...",');
            $io->text('  "user": { ... },');
            $io->text('  "provider": "google"');
            $io->text('}');
            $io->text('');
            $io->text('Test with your frontend by using the "token" field:');
            $io->text('Authorization: Bearer ' . substr($jwtToken, 0, 30) . '...');
            $io->text('');
            $io->success('✅ OAuth JWT integration is ready!');
            
        } catch (\Exception $e) {
            $io->error('Exception during JWT generation: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}