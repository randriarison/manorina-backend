<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:verify-admin-user',
    description: 'Verify the admin user exists and can authenticate'
)]
class VerifyAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Verifying Admin User');
        
        $email = 'onyvoni@gmail.com';
        $plainPassword = 'ony@man0r1n@';
        
        // Find the user
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if (!$user) {
            $io->error("Admin user with email '{$email}' not found in database!");
            return Command::FAILURE;
        }
        
        $io->success("Admin user found in database!");
        
        // Verify user details
        $io->section('User Verification Details');
        
        $table = [
            ['Property', 'Value', 'Status'],
            ['Email', $user->getEmail(), '✅'],
            ['Name', $user->getName() ?? 'N/A', $user->getName() ? '✅' : '❌'],
            ['Has ROLE_ADMIN', in_array('ROLE_ADMIN', $user->getRoles()) ? 'Yes' : 'No', in_array('ROLE_ADMIN', $user->getRoles()) ? '✅' : '❌'],
            ['Email Verified', $user->isEmailVerified() ? 'Yes' : 'No', $user->isEmailVerified() ? '✅' : '❌'],
            ['Has Password', $user->getPassword() ? 'Yes' : 'No', $user->getPassword() ? '✅' : '❌'],
        ];
        
        $io->table(['Property', 'Value', 'Status'], $table);
        
        // Test password verification
        $io->section('Password Verification');
        
        if ($user->getPassword() && $this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            $io->success('✅ Password verification successful!');
        } else {
            $io->error('❌ Password verification failed!');
            return Command::FAILURE;
        }
        
        // Show authentication info
        $io->section('Authentication Information');
        $io->text('The admin user can authenticate with:');
        $io->definitionList(
            ['Email' => $user->getEmail()],
            ['Password' => $plainPassword],
            ['Roles' => implode(', ', $user->getRoles())]
        );
        
        $io->section('API Testing');
        $io->text('You can test authentication using these API endpoints:');
        $io->listing([
            'POST /api/auth/login - Regular login',
            'GET /api/articles/only-mine - Requires authentication',
            'POST /api/articles - Requires authentication',
        ]);
        
        $io->success('Admin user verification completed successfully!');
        
        return Command::SUCCESS;
    }
}