<?php

namespace App\Security\Voter;

use App\Entity\Article;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ArticleVoter extends Voter
{
    public const EDIT = 'ARTICLE_EDIT';
    public const VIEW = 'ARTICLE_VIEW';
    public const DELETE = 'ARTICLE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW, self::DELETE])
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If the user is not authenticated, deny access
        if (!$user instanceof User) {
            return false;
        }

        /** @var Article $article */
        $article = $subject;

        switch ($attribute) {
            case self::EDIT:
            case self::DELETE:
                return $this->canEdit($article, $user);
            case self::VIEW:
                return $this->canView($article, $user);
        }

        return false;
    }

    private function canEdit(Article $article, User $user): bool
    {
        // User can edit if they are the original author
        if ($article->getAuthor() === $user) {
            return true;
        }

        // Or if they have moderator/admin role
        return in_array('ROLE_MODERATOR', $user->getRoles()) 
            || in_array('ROLE_ADMIN', $user->getRoles());
    }

    private function canView(Article $article, User $user): bool
    {
        // For now, all authenticated users can view articles
        // You can modify this based on your business logic
        // For example, check if article is published or if user is author
        return true;
    }
}