<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:jwt-security',
    description: 'Test JWT token generation for security testing'
)]
class TestJwtSecurityCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('JWT Security Testing Helper');

        // Find or create a test user
        $user = $this->userRepository->findOneBy(['email' => 'test@example.com']);
        
        if (!$user) {
            $io->section('Creating Test User');
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setPassword('$2y$13$dummy.hashed.password.for.testing.purposes');
            $user->setRoles(['ROLE_USER']);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $io->success('Test user created with email: test@example.com');
        } else {
            $io->info('Test user already exists with email: test@example.com');
        }

        $io->section('Testing Instructions');
        $io->text('1. Get a JWT token by making a POST request to /api/auth/login:');
        $io->text('   POST /api/auth/login');
        $io->text('   Content-Type: application/json');
        $io->text('   {');
        $io->text('     "email": "test@example.com",');
        $io->text('     "password": "your_actual_password"');
        $io->text('   }');
        $io->newLine();
        
        $io->text('2. Use the returned token in the Authorization header:');
        $io->text('   GET /api/articles');
        $io->text('   Authorization: Bearer YOUR_JWT_TOKEN_HERE');
        $io->newLine();
        
        $io->text('3. Check that the ArticleExtension now properly detects the authenticated user');
        
        $io->warning('Make sure to set the correct password for the test user or create a new one through the registration endpoint');

        return Command::SUCCESS;
    }
}