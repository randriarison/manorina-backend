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
    description: 'Create multiple admin users from predefined list, skipping existing users'
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
        
        $io->title('Creating Admin Users');
        
        // Array of users to create - add new users to this list
        $usersToCreate = [
           [
                'email' => 'onyvoni@gmail.com',
                'password' => 'ony@man0r1n@',
                'name' => 'ony'
            ],
            [
                'email' => 'randriarivo@yahoo.fr',
                'password' => 'r1v0@admIn123!',
                'name' => 'Rivo'
            ],
            [
                'email' => 'raaina33@hotmail.com',
                'password' => 'raAinA!33',
                'name' => 'Aina'
            ],
            [
                'email' => 'chrisrivony@yahoo.fr',
                'password' => 'ony@man0r1n@',
                'name' => 'Christiane'
            ],
            [
                'email' => 'onyrandri@yahoo.fr',
                'password' => 'ony@man0r1n@',
                'name' => 'Vero'
            ],
            [
                'email' => 'vonimbero@yahoo.fr',
                'password' => 'ony@man0r1n@',
                'name' => 'Vony'
            ],
            [ 
	            'email' => 'tojo.fehizoro@gmail.com',
                'password' => 'toj0@anA0123t',
                'name' => 'Noé'
            ],
            [
                'email' => 'mihossiraman@gmail.com',
                'password' => 'm1h0ssI&',
                'name' => 'Olivah'
            ],
            [
                'email' => 'mahalisoa.berhault@gmail.com',
                'password' => 'Mah4L!s0',
                'name' => 'Mah'

            ],
            [
                'email' => 'nanouh.rasamoely@gmail.com',
                'password' => 'N4n0uh@2',
                'name' => 'Noro'

            ],
            [
                'email' => 'parfait.andry@gmail.com',
                'password' => 'P4rf@it7',
                'name' => 'Andry'
            ],
            [
                'email' => 'ioty.rabarone@gmail.com',
                'password' => 'I0ty*R3b',
                'name' => 'IMR'
            ],
            [
                'email' => 'herininahary@gmail.com',
                'password' => 'H3r1n!H4',
                'name' => 'Hery'
            ],
            [
                'email' => 'm.rakotonj@gmail.com',
                'password' => 'M*R4k0t9',
                'name' => 'Mickaël'
            ],
            [
                'email' => 'tatianah.Andriana@gmail.com',
                'password' => 'T4t1@nAh',
                'name' => 'Tatianah'
            ],
            [
                'email' => 'serge27raoni@yahoo.fr',
                'password' => 'S3rg27@!',
                'name' => 'TatianahSergo'
            ],
            [
                'email' => 'bako.randriarison@gmail.com',
                'password' => 'B4k0R@nd!',
                'name' => 'Bako'
            ],
            [
                'email' => 'patricia.ravelona@gmail.com',
                'password' => 'P4tr!c1@2023',
                'name' => 'Lalaina'
            ],
            [
                'email' => 'brabemanantsoa61@gmail.com',
                'password' => 'Br4b3M@61',
                'name' => 'Benja'
            ],
            [
                'email' => 'romeorakotomanga@gmail.com',
                'password' => 'R0m30@2021',
                'name' => 'Romeo'
            ],
            [
                'email' => 'aovpm31@gmail.com',
                'password' => 'A0vP@ssw0rd31',
                'name' => 'Association Observatoire de la Vie Publique de Madagascar'
            ],
            [
                'email' => 'fanirisoa.zazaravaka@grmail.com',
                'password' => 'Le@FanZ2024',
                'name' => 'Léa'
            ],
            [
                'email' => 'matiojoachin2015@gmailcom',
                'password' => 'mat!oJ0*15',
                'name' => 'Matio'
            ],
            [
                'email' => 'ignace.randriamami@gmail.com',
                'password' => 'Ign@c3R4nd!?',
                'name' => 'Mamy'
            ],
            [
                'email' => 'montaharison@gmail.com',
                'password' => 'M0nt@H@r1s0n',
                'name' => 'Arison'
            ],
            [
                'email' => 'iarivelo_@hotmail.com',
                'password' => 'Iar!v3l0',
                'name' => 'Mialy'
            ],
        ];
        
        $userRepository = $this->entityManager->getRepository(User::class);
        $createdUsers = [];
        $existingUsers = [];
        $failedUsers = [];
        
        $io->text(sprintf('Processing %d user(s)...', count($usersToCreate)));
        $io->newLine();
        
        foreach ($usersToCreate as $userData) {
            $email = $userData['email'];
            $password = $userData['password'];
            $name = $userData['name'];
            
            $io->text("Checking user: {$email}");
            
            // Check if user already exists
            $existingUser = $userRepository->findOneBy(['email' => $email]);
            
            if ($existingUser) {
                $existingUsers[] = $userData;
                $io->text("  ➤ User already exists, skipping...");
                continue;
            }
            
            try {
                // Create new admin user
                $user = new User();
                $user->setEmail($email);
                $user->setName($name);
                $user->setRoles(['ROLE_MODERATOR']);
                $user->setIsEmailVerified(true); // Admin user should be verified
                $user->setEmailVerifiedAt(new \DateTimeImmutable());
                
                // Hash the password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
                
                $this->entityManager->persist($user);
                
                $createdUsers[] = [
                    'user' => $user,
                    'password' => $password
                ];
                
                $io->text("  ✅ User prepared for creation");
                
            } catch (\Exception $e) {
                $failedUsers[] = [
                    'userData' => $userData,
                    'error' => $e->getMessage()
                ];
                $io->text("  ❌ Failed to prepare user: " . $e->getMessage());
            }
        }
        
        // Flush all changes at once for better performance
        if (!empty($createdUsers)) {
            try {
                $this->entityManager->flush();
                $io->newLine();
                $io->success(sprintf('Successfully created %d admin user(s)!', count($createdUsers)));
                
                // Display created users
                $this->displayCreatedUsers($io, $createdUsers);
                
            } catch (\Exception $e) {
                $io->error('Failed to save users to database: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }
        
        // Display summary
        $this->displaySummary($io, $createdUsers, $existingUsers, $failedUsers);
        
        return Command::SUCCESS;
    }
    
    private function displayCreatedUsers(SymfonyStyle $io, array $createdUsers): void
    {
        $io->section('Created Admin Users');
        
        $tableRows = [];
        foreach ($createdUsers as $userData) {
            $user = $userData['user'];
            $password = $userData['password'];
            
            $tableRows[] = [
                $user->getEmail(),
                $user->getName(),
                implode(', ', $user->getRoles()),
                $password,
                $user->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }
        
        $io->table(
            ['Email', 'Name', 'Roles', 'Password', 'Created At'],
            $tableRows
        );
        
        $io->section('Login Information');
        $io->text('You can now log in using any of the above credentials.');
        $io->note('Keep these passwords secure and consider changing them after first login.');
    }
    
    private function displaySummary(SymfonyStyle $io, array $createdUsers, array $existingUsers, array $failedUsers): void
    {
        $io->section('Summary');
        
        $io->definitionList(
            ['Total Processed' => count($createdUsers) + count($existingUsers) + count($failedUsers)],
            ['Successfully Created' => count($createdUsers)],
            ['Already Existing (Skipped)' => count($existingUsers)],
            ['Failed' => count($failedUsers)]
        );
        
        if (!empty($existingUsers)) {
            $io->section('Existing Users (Skipped)');
            foreach ($existingUsers as $userData) {
                $io->text("• {$userData['email']} ({$userData['name']})");
            }
        }
        
        if (!empty($failedUsers)) {
            $io->section('Failed Users');
            foreach ($failedUsers as $failedUser) {
                $userData = $failedUser['userData'];
                $error = $failedUser['error'];
                $io->text("• {$userData['email']} - Error: {$error}");
            }
        }
        
        if (count($createdUsers) > 0) {
            $io->success('Admin user creation completed successfully!');
        } elseif (count($existingUsers) > 0 && count($failedUsers) === 0) {
            $io->note('All users already exist in the database.');
        } else {
            $io->warning('No users were created.');
        }
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