<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailService;
use App\Service\EmailValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailService $emailService,
        private EmailValidationService $emailValidationService,
        private ValidatorInterface $validator,
        private JWTTokenManagerInterface $jwtManager
    ) {}

    /**
     * User Registration
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!isset($data['email']) || !isset($data['password'])) {
                return new JsonResponse([
                    'error' => 'Missing required fields',
                    'message' => 'Email and password are required'
                ], 400);
            }

            $email = trim($data['email']);
            $password = $data['password'];
            $name = $data['name'] ?? null;

            // Validate email
            if (!$this->emailValidationService->isValidEmail($email)) {
                $errors = $this->emailValidationService->getValidationErrors($email);
                return new JsonResponse([
                    'error' => 'Invalid email',
                    'message' => implode(' ', $errors)
                ], 400);
            }

            // Check if user already exists
            $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                return new JsonResponse([
                    'error' => 'User already exists',
                    'message' => 'An account with this email address already exists'
                ], 409);
            }

            // Validate password strength
            if (!$this->isPasswordStrong($password)) {
                return new JsonResponse([
                    'error' => 'Weak password',
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character'
                ], 400);
            }

            // Create new user
            $user = new User();
            $user->setEmail($email)
                 ->setName($name)
                 ->setRoles(['ROLE_USER']);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Validate entity
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse([
                    'error' => 'Validation failed',
                    'message' => implode(', ', $errorMessages)
                ], 400);
            }

            // Save user
            $this->em->persist($user);
            $this->em->flush();

            // Send verification email
            try {
                $this->emailService->sendEmailVerification($user);
            } catch (\Exception $e) {
                // Log the error but don't fail registration
                error_log('Failed to send verification email: ' . $e->getMessage());
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Registration successful. Please check your email to verify your account.',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'email_verified' => $user->isEmailVerified(),
                    'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ]
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Registration failed',
                'message' => 'An error occurred during registration'
            ], 500);
        }
    }

    /**
     * Email Verification
     */
    #[Route('/verify-email', name: 'verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $token = $data['token'] ?? null;

            if (!$token) {
                return new JsonResponse([
                    'error' => 'Missing token',
                    'message' => 'Verification token is required'
                ], 400);
            }

            $user = $this->em->getRepository(User::class)->findOneBy([
                'emailVerificationToken' => $token
            ]);

            if (!$user) {
                return new JsonResponse([
                    'error' => 'Invalid token',
                    'message' => 'Invalid or expired verification token'
                ], 400);
            }

            if ($user->isEmailVerified()) {
                return new JsonResponse([
                    'error' => 'Already verified',
                    'message' => 'Email is already verified'
                ], 400);
            }

            // Verify the email
            $user->markEmailAsVerified();
            $user->updateTimestamps();

            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Email verified successfully',
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'email_verified' => true,
                    'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Verification failed',
                'message' => 'An error occurred during email verification'
            ], 500);
        }
    }

    /**
     * Create Password with Verification Token
     */
    #[Route('/create-password', name: 'create_password', methods: ['POST'])]
    public function createPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $token = $data['token'] ?? null;
            $password = $data['password'] ?? null;

            if (!$token || !$password) {
                return new JsonResponse([
                    'error' => 'Missing fields',
                    'message' => 'Token and password are required'
                ], 400);
            }

            $user = $this->em->getRepository(User::class)->findOneBy([
                'emailVerificationToken' => $token
            ]);

            if (!$user) {
                return new JsonResponse([
                    'error' => 'Invalid token',
                    'message' => 'Invalid or expired verification token'
                ], 400);
            }

            if ($user->isEmailVerified() && $user->getPassword()) {
                return new JsonResponse([
                    'error' => 'Already completed',
                    'message' => 'Account setup is already complete'
                ], 400);
            }

            // Validate password strength
            if (!$this->isPasswordStrong($password)) {
                return new JsonResponse([
                    'error' => 'Weak password',
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character'
                ], 400);
            }

            // Hash and set password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            
            // Mark email as verified
            $user->markEmailAsVerified();
            $user->updateTimestamps();

            $this->em->flush();

            // Generate JWT token for automatic login
            $jwtToken = $this->jwtManager->create($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Password created and email verified successfully',
                'token' => $jwtToken,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'email_verified' => true,
                    'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
                    'roles' => $user->getRoles()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Password creation failed',
                'message' => 'An error occurred while creating your password'
            ], 500);
        }
    }

    /**
     * User Login
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['email']) || !isset($data['password'])) {
                return new JsonResponse([
                    'error' => 'Missing credentials',
                    'message' => 'Email and password are required'
                ], 400);
            }

            $email = trim($data['email']);
            $password = $data['password'];

            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
                return new JsonResponse([
                    'error' => 'Invalid credentials',
                    'message' => 'Invalid email or password'
                ], 401);
            }

            if (!$user->isEmailVerified()) {
                return new JsonResponse([
                    'error' => 'Email not verified',
                    'message' => 'Please verify your email address before logging in'
                ], 403);
            }

            // Update last login
            $user->updateLastLogin();
            $this->em->flush();

            // Generate JWT token
            $token = $this->jwtManager->create($user);

            return new JsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                    'email_verified' => $user->isEmailVerified(),
                    'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                    'roles' => $user->getRoles()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Login failed',
                'message' => 'An error occurred during login'
            ], 500);
        }
    }

    /**
     * Request Password Reset
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;

            if (!$email) {
                return new JsonResponse([
                    'error' => 'Missing email',
                    'message' => 'Email address is required'
                ], 400);
            }

            $user = $this->em->getRepository(User::class)->findOneBy(['email' => trim($email)]);

            // Always return success for security (don't reveal if email exists)
            if (!$user) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'If an account with this email exists, a password reset link has been sent.'
                ]);
            }

            // Generate password reset token
            $user->generatePasswordResetToken();
            $user->updateTimestamps();

            $this->em->flush();

            // Send password reset email
            try {
                $this->emailService->sendPasswordReset($user);
            } catch (\Exception $e) {
                error_log('Failed to send password reset email: ' . $e->getMessage());
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'If an account with this email exists, a password reset link has been sent.'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Request failed',
                'message' => 'An error occurred while processing your request'
            ], 500);
        }
    }

    /**
     * Reset Password
     */
    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $token = $data['token'] ?? null;
            $newPassword = $data['password'] ?? null;

            if (!$token || !$newPassword) {
                return new JsonResponse([
                    'error' => 'Missing fields',
                    'message' => 'Token and new password are required'
                ], 400);
            }

            $user = $this->em->getRepository(User::class)->findOneBy([
                'passwordResetToken' => $token
            ]);

            if (!$user || !$user->isPasswordResetTokenValid()) {
                return new JsonResponse([
                    'error' => 'Invalid token',
                    'message' => 'Invalid or expired reset token'
                ], 400);
            }

            // Validate new password strength
            if (!$this->isPasswordStrong($newPassword)) {
                return new JsonResponse([
                    'error' => 'Weak password',
                    'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, one number, and one special character'
                ], 400);
            }

            // Update password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->clearPasswordResetToken();
            $user->updateTimestamps();

            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Reset failed',
                'message' => 'An error occurred while resetting your password'
            ], 500);
        }
    }

    /**
     * Resend Verification Email
     */
    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $email = $data['email'] ?? null;

            if (!$email) {
                return new JsonResponse([
                    'error' => 'Missing email',
                    'message' => 'Email address is required'
                ], 400);
            }

            $user = $this->em->getRepository(User::class)->findOneBy(['email' => trim($email)]);

            if (!$user) {
                return new JsonResponse([
                    'error' => 'User not found',
                    'message' => 'No account found with this email address'
                ], 404);
            }

            if ($user->isEmailVerified()) {
                return new JsonResponse([
                    'error' => 'Already verified',
                    'message' => 'Email is already verified'
                ], 400);
            }

            // Generate new verification token
            $user->generateEmailVerificationToken();
            $user->updateTimestamps();

            $this->em->flush();

            // Send verification email
            try {
                $this->emailService->sendEmailVerification($user);
            } catch (\Exception $e) {
                error_log('Failed to send verification email: ' . $e->getMessage());
                return new JsonResponse([
                    'error' => 'Email failed',
                    'message' => 'Failed to send verification email'
                ], 500);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Verification email sent successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Request failed',
                'message' => 'An error occurred while resending verification email'
            ], 500);
        }
    }

    /**
     * Get Current User (requires JWT token)
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing authentication token'
            ], 401);
        }

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'email_verified' => $user->isEmailVerified(),
                'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
                'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updated_at' => $user->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'roles' => $user->getRoles()
            ]
        ]);
    }

    private function isPasswordStrong(string $password): bool
    {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}