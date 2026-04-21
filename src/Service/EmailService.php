<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $parameterBag
    ) {}

    public function sendEmailVerification(User $user): void
    {
        $verificationUrl = sprintf(
            '%s/create-password?token=%s',
            ($_ENV['FRONTEND_URL'] ?? 'http://localhost:8080') . '/verify-email',
            $user->getEmailVerificationToken()
        );

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['FROM_EMAIL'] ?? 'noreply@manorina.com', $_ENV['FROM_NAME'] ?? 'Manorina'))
            ->to($user->getEmail())
            ->subject('Verify your email address - Manorina')
            ->htmlTemplate('emails/email_verification.html.twig')
            ->context([
                'user' => $user,
                'verification_url' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    public function sendPasswordReset(User $user): void
    {
        $resetUrl = sprintf(
            '%s/reset-password?token=%s',
            $_ENV['FRONTEND_URL'] ?? 'http://localhost:8080',
            $user->getPasswordResetToken()
        );

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['FROM_EMAIL'] ?? 'noreply@manorina.com', $_ENV['FROM_NAME'] ?? 'Manorina'))
            ->to($user->getEmail())
            ->subject('Password Reset Request - Manorina')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'user' => $user,
                'reset_url' => $resetUrl,
            ]);

        $this->mailer->send($email);
    }
}