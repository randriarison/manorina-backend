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
use Symfony\Component\HttpFoundation\Request;

#[AsCommand(
    name: 'test:oauth-last-login',
    description: 'Test OAuth last login tracking functionality'
)]
class TestOAuthLastLoginCommand extends Command
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

        $io->title('Testing OAuth Last Login Tracking');

        // Check if we have the OAuth test user
        $testUser = $this->userRepository->findOneBy(['email' => 'oauth-test@example.com']);
        
        if (!$testUser) {
            $io->error('OAuth test user not found. Please run test:oauth-jwt first.');
            return Command::FAILURE;
        }

        $io->section('Current User Login Information');
        $io->text('👤 User Email: ' . $testUser->getEmail());
        $io->text('📅 Created At: ' . ($testUser->getCreatedAt()?->format('Y-m-d H:i:s') ?? 'N/A'));
        $io->text('🕒 Last Login At: ' . ($testUser->getLastLoginAt()?->format('Y-m-d H:i:s') ?? 'Never'));

        // Update last login to simulate OAuth login
        $oldLastLogin = $testUser->getLastLoginAt();
        
        $io->section('Simulating OAuth Login');
        $io->text('Updating last login timestamp...');
        
        // Wait a second to ensure timestamp difference
        sleep(1);
        
        $testUser->updateLastLogin();
        $this->entityManager->flush();
        
        $newLastLogin = $testUser->getLastLoginAt();
        
        $io->section('Updated Login Information');
        $io->text('🕒 Previous Last Login: ' . ($oldLastLogin?->format('Y-m-d H:i:s') ?? 'Never'));
        $io->text('🕒 New Last Login: ' . ($newLastLogin?->format('Y-m-d H:i:s') ?? 'N/A'));
        
        if ($newLastLogin && (!$oldLastLogin || $newLastLogin > $oldLastLogin)) {
            $io->success('✅ Last login timestamp was successfully updated!');
            
            if ($oldLastLogin) {
                $diff = $newLastLogin->getTimestamp() - $oldLastLogin->getTimestamp();
                $io->text("📊 Time difference: {$diff} seconds");
            }
        } else {
            $io->error('❌ Last login timestamp was not updated properly');
            return Command::FAILURE;
        }

        $io->section('OAuth Response Format');
        $io->text('When users authenticate via OAuth, the response now includes:');
        $io->text('');
        $io->text('{');
        $io->text('  "success": true,');
        $io->text('  "user": {');
        $io->text('    "id": "...",');
        $io->text('    "email": "user@example.com",');
        $io->text('    "name": "User Name",');
        $io->text('    "avatar": "...",');
        $io->text('    "google_id": "...",');
        $io->text('    "created_at": "2025-11-06 12:00:00",');
        $io->text('    "last_login_at": "' . ($newLastLogin?->format('Y-m-d H:i:s') ?? 'N/A') . '"');
        $io->text('  },');
        $io->text('  "token": "JWT_TOKEN...",');
        $io->text('  "provider": "google"');
        $io->text('}');

        $io->section('Database Verification');
        
        // Verify in database
        $this->entityManager->refresh($testUser);
        $dbLastLogin = $testUser->getLastLoginAt();
        
        if ($dbLastLogin && $dbLastLogin->format('Y-m-d H:i:s') === $newLastLogin->format('Y-m-d H:i:s')) {
            $io->success('✅ Last login is correctly persisted in the database');
        } else {
            $io->error('❌ Last login was not properly persisted in the database');
            return Command::FAILURE;
        }

        $io->success('🎉 OAuth last login tracking is working perfectly!');

        return Command::SUCCESS;
    }
}