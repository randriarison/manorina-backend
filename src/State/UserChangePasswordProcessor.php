<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserChangePasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        /** @var User $user */
        $user = $data;
        
        // Get the current authenticated user
        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }
        
        // Ensure user can only change their own password
        if ($currentUser->getId()->toString() !== $user->getId()->toString()) {
            throw new AccessDeniedException('You can only change your own password');
        }
        
        // Get request data
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new BadRequestHttpException('Request not available');
        }
        
        $requestData = json_decode($request->getContent(), true);
        if (!$requestData) {
            throw new BadRequestHttpException('Invalid JSON data');
        }
        
        // Validate required fields
        if (!isset($requestData['currentPassword']) || !isset($requestData['newPassword'])) {
            throw new BadRequestHttpException('Both currentPassword and newPassword are required');
        }
        
        $currentPassword = $requestData['currentPassword'];
        $newPassword = $requestData['newPassword'];
        
        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new BadRequestHttpException('Current password is incorrect');
        }
        
        // Validate new password strength
        if (!$this->isPasswordStrong($newPassword)) {
            throw new BadRequestHttpException(
                'Password must be at least 8 characters long and contain at least 1 lowercase letter, 1 uppercase letter, 1 number, and 2 special characters'
            );
        }
        
        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        
        // Update timestamps
        $user->updateTimestamps();
        
        // Save changes
        $this->entityManager->flush();
        
        return $user;
    }
    
    /**
     * Validate password strength requirements
     */
    private function isPasswordStrong(string $password): bool
    {
        // At least 8 characters
        if (strlen($password) < 8) {
            return false;
        }
        
        // At least 1 lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // At least 1 uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // At least 1 number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // At least 2 special characters
        $specialCharCount = preg_match_all('/[^a-zA-Z0-9]/', $password);
        if ($specialCharCount < 2) {
            return false;
        }
        
        return true;
    }
}