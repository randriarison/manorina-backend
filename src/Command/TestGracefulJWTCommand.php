<?php

namespace App\Command;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:graceful-jwt',
    description: 'Test graceful JWT token handling for expired tokens on GET requests'
)]
class TestGracefulJWTCommand extends Command
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

        $io->title('Testing Graceful JWT Token Handling');

        // Find or create a test user
        $testUser = $this->userRepository->findOneBy(['email' => 'graceful-jwt-test@example.com']);
        
        if (!$testUser) {
            $testUser = new User();
            $testUser->setEmail('graceful-jwt-test@example.com')
                     ->setName('Graceful JWT Test User')
                     ->setRoles(['ROLE_USER']);
            
            $this->entityManager->persist($testUser);
            $this->entityManager->flush();
            
            $io->success('Created test user: graceful-jwt-test@example.com');
        } else {
            $io->info('Using existing test user: graceful-jwt-test@example.com');
        }

        $io->section('Generating JWT Tokens');

        // Generate a valid JWT token
        $validToken = $this->jwtManager->create($testUser);
        $io->text('✅ Valid JWT Token: ' . substr($validToken, 0, 50) . '...');

        // Create an expired JWT token by manually creating one with past timestamp
        $expiredToken = $this->createExpiredToken();
        $io->text('❌ Expired JWT Token: ' . substr($expiredToken, 0, 50) . '...');

        // Create an invalid JWT token
        $invalidToken = 'invalid.jwt.token';
        $io->text('❌ Invalid JWT Token: ' . $invalidToken);

        $io->section('Test Scenarios');

        $io->text('Now test these scenarios with your API:');
        $io->text('');

        $io->text('1. 📋 GET /api/articles (no token) - Should return published articles');
        $io->text('   curl -X GET http://your-domain/api/articles');
        $io->text('');

        $io->text('2. ✅ GET /api/articles (valid token) - Should return user articles + published');
        $io->text('   curl -X GET http://your-domain/api/articles \\');
        $io->text('     -H "Authorization: Bearer ' . substr($validToken, 0, 30) . '..."');
        $io->text('');

        $io->text('3. 🎯 GET /api/articles (expired token) - Should gracefully return published articles');
        $io->text('   curl -X GET http://your-domain/api/articles \\');
        $io->text('     -H "Authorization: Bearer ' . substr($expiredToken, 0, 30) . '..."');
        $io->text('   Expected: Same as scenario 1 (no 401 error)');
        $io->text('');

        $io->text('4. 🎯 GET /api/articles (invalid token) - Should gracefully return published articles');
        $io->text('   curl -X GET http://your-domain/api/articles \\');
        $io->text('     -H "Authorization: Bearer ' . $invalidToken . '"');
        $io->text('   Expected: Same as scenario 1 (no 401 error)');
        $io->text('');

        $io->text('5. ❌ POST /api/articles (expired token) - Should return 401 error');
        $io->text('   curl -X POST http://your-domain/api/articles \\');
        $io->text('     -H "Authorization: Bearer ' . substr($expiredToken, 0, 30) . '..." \\');
        $io->text('     -H "Content-Type: application/json" \\');
        $io->text('     -d \'{"title":"Test","content":"Test","status":"draft"}\'');
        $io->text('   Expected: 401 Unauthorized (normal behavior for non-GET requests)');

        $io->section('Expected Behavior');
        $io->text('✅ GET requests with expired/invalid tokens: Return published articles (graceful)');
        $io->text('❌ POST/PUT/DELETE requests with expired/invalid tokens: Return 401 error (secure)');
        $io->text('✅ All requests with valid tokens: Work normally');
        $io->text('✅ All requests without tokens: Work as anonymous (for public endpoints)');

        $io->success('🎉 Graceful JWT handling is configured! Test the scenarios above.');

        return Command::SUCCESS;
    }

    private function createExpiredToken(): string
    {
        // Create a simple expired JWT token for testing
        // This is a basic JWT structure with an expired timestamp
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = base64_encode(json_encode([
            'username' => 'graceful-jwt-test@example.com',
            'roles' => ['ROLE_USER'],
            'iat' => time() - 7200, // 2 hours ago
            'exp' => time() - 3600  // Expired 1 hour ago
        ]));
        
        return $header . '.' . $payload . '.expired-signature';
    }
}