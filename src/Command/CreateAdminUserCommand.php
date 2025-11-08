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
    name: 'app:create-admin-user',
    description: 'Create an admin user with specified credentials'
)]
class CreateAdminUserCommand extends Command
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
        
        $io->title('Creating Admin User');
        
        $email = 'onyvoni@gmail.com';
        $password = 'ony@man0r1n@';
        $name = 'ony';
        
        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        
        if ($existingUser) {
            $io->warning("User with email '{$email}' already exists!");
            
            // Ask if we should update the existing user
            if ($io->confirm('Do you want to update the existing user to admin role?', false)) {
                $existingUser->setRoles(['ROLE_ADMIN']);
                $existingUser->setName($name);
                
                // Update password
                $hashedPassword = $this->passwordHasher->hashPassword($existingUser, $password);
                $existingUser->setPassword($hashedPassword);
                $existingUser->setUpdatedAt(new \DateTimeImmutable());
                
                $this->entityManager->flush();
                
                $io->success("Existing user updated to admin successfully!");
                $this->displayUserInfo($io, $existingUser, $password);
                
                return Command::SUCCESS;
            } else {
                $io->note('Operation cancelled. No changes made.');
                return Command::SUCCESS;
            }
        }
        
        // Create new admin user
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setIsEmailVerified(true); // Admin user should be verified
        $user->setEmailVerifiedAt(new \DateTimeImmutable());
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            $io->success('Admin user created successfully!');
            $this->displayUserInfo($io, $user, $password);
            
        } catch (\Exception $e) {
            $io->error('Failed to create admin user: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function displayUserInfo(SymfonyStyle $io, User $user, string $plainPassword): void
    {
        $io->section('Admin User Details');
        
        $io->definitionList(
            ['Email' => $user->getEmail()],
            ['Name' => $user->getName()],
            ['Roles' => implode(', ', $user->getRoles())],
            ['Password' => $plainPassword],
            ['Email Verified' => $user->isEmailVerified() ? 'Yes' : 'No'],
            ['Created At' => $user->getCreatedAt()?->format('Y-m-d H:i:s')],
            ['Updated At' => $user->getUpdatedAt()?->format('Y-m-d H:i:s')]
        );
        
        $io->section('Login Information');
        $io->text('You can now log in using:');
        $io->text("Email: {$user->getEmail()}");
        $io->text("Password: {$plainPassword}");
        
        $io->note('Keep this password secure and consider changing it after first login.');
    }
}