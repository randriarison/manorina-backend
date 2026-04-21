<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ProcessorInterface $decoratedProcessor,
        private EmailService $emailService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        /** @var User $user */
        $user = $data;
        
        $isNewUser = $operation instanceof \ApiPlatform\Metadata\Post;
        
        // Hash password if provided and it's not already hashed
        if ($user->getPassword() && !$this->isPasswordHashed($user->getPassword())) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);
        }
        
        // Set default role if no roles are provided (only for POST operations)
        if ($isNewUser) {
            if (empty($user->getRoles()) || $user->getRoles() === ['ROLE_USER']) {
                // If getRoles() returns ['ROLE_USER'], it means the roles array was empty
                // and the ROLE_USER was added by the getRoles() method
                // We need to check the actual roles property
                $reflection = new \ReflectionClass($user);
                $rolesProperty = $reflection->getProperty('roles');
                $rolesProperty->setAccessible(true);
                $actualRoles = $rolesProperty->getValue($user);
                
                if (empty($actualRoles)) {
                    $user->setRoles(['ROLE_USER']);
                }
            }
            
            // Ensure isEmailVerified is false for new users
            $user->setIsEmailVerified(false);
        }
        
        // Call the decorated processor to handle the actual persistence
        $result = $this->decoratedProcessor->process($user, $operation, $uriVariables, $context);
        
        // Send verification email after successful user creation
        if ($isNewUser) {
            $this->emailService->sendEmailVerification($result);
        }
        
        return $result;
    }
    
    /**
     * Check if the password is already hashed (bcrypt/argon2 hash)
     */
    private function isPasswordHashed(string $password): bool
    {
        // Check if it's a bcrypt hash ($2y$) or argon2 hash ($argon2)
        return str_starts_with($password, '$2y$') || 
               str_starts_with($password, '$2a$') || 
               str_starts_with($password, '$2b$') ||
               str_starts_with($password, '$argon2');
    }
}