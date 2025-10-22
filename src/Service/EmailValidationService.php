<?php

namespace App\Service;

class EmailValidationService
{
    private const BLOCKED_DOMAINS = [
        'yopmail.com',
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'tempmail.org',
        'throwaway.email',
        'temp-mail.org',
        'getnada.com',
        'maildrop.cc',
        'dispostable.com',
        // Add more disposable email domains as needed
    ];

    public function isValidEmail(string $email): bool
    {
        // Basic email format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Extract domain from email
        $domain = strtolower(substr(strrchr($email, '@'), 1));

        // Check if domain is in blocked list
        if (in_array($domain, self::BLOCKED_DOMAINS, true)) {
            return false;
        }

        // Additional domain validation (check if domain has MX record)
        if (!$this->hasMxRecord($domain)) {
            return false;
        }

        return true;
    }

    public function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        return in_array($domain, self::BLOCKED_DOMAINS, true);
    }

    private function hasMxRecord(string $domain): bool
    {
        try {
            return checkdnsrr($domain, 'MX');
        } catch (\Exception $e) {
            // If we can't check DNS, allow it (avoid false negatives)
            return true;
        }
    }

    public function getValidationErrors(string $email): array
    {
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
            return $errors;
        }

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        if (in_array($domain, self::BLOCKED_DOMAINS, true)) {
            $errors[] = 'Disposable email addresses are not allowed. Please use a valid email address.';
        }

        if (!$this->hasMxRecord($domain)) {
            $errors[] = 'Email domain does not exist or cannot receive emails.';
        }

        return $errors;
    }
}