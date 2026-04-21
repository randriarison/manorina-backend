<?php

namespace App\Security\Voter;

use App\Entity\Article;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class ArticleVoter extends Voter
{
    public const EDIT = 'ARTICLE_EDIT';
    public const EDIT_STATUS = 'ARTICLE_EDIT_STATUS';
    public const VIEW = 'ARTICLE_VIEW';
    public const DELETE = 'ARTICLE_DELETE';

    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::EDIT_STATUS, self::VIEW, self::DELETE])
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        /** @var Article $article */
        $article = $subject;

        // Special handling for VIEW - allow unauthenticated users to view published articles
        if ($attribute === self::VIEW) {
            return $this->canView($article, $user instanceof User ? $user : null);
        }

        // For all other operations, user must be authenticated
        if (!$user instanceof User) {
            return false;
        }

        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($article, $user);
            case self::EDIT_STATUS:
                return $this->canEditStatus($article, $user);
            case self::DELETE:
                return $this->canDelete($article, $user);
        }

        return false;
    }

    private function canEdit(Article $article, User $user): bool
    {
        // Only the author can edit their own article (full edit access)
        return $article->getAuthor() === $user;
    }

    private function canEditStatus(Article $article, User $user): bool
    {
        // User can edit status if they are the original author
        if ($article->getAuthor() === $user) {
            return true;
        }

        // Moderators and above (including admins) can edit status of any article
        return $this->authorizationChecker->isGranted('ROLE_MODERATOR');
    }

    private function canDelete(Article $article, User $user): bool
    {
        // User can delete if they are the original author
        if ($article->getAuthor() === $user) {
            return true;
        }

        // Only admins can delete other users' articles (moderators cannot)
        return $this->authorizationChecker->isGranted('ROLE_ADMIN');
    }

    private function canView(Article $article, ?User $user): bool
    {
        // Published articles can be viewed by anyone (including unauthenticated users)
        if ($article->getStatus() && $article->getStatus()->value === 'published') {
            return true;
        }

        // For non-published articles, user must be authenticated
        if (!$user instanceof User) {
            return false;
        }

        // Authors can always view their own articles regardless of status
        if ($article->getAuthor() === $user) {
            return true;
        }

        // Moderators and above (including admins) can view all articles
        return $this->authorizationChecker->isGranted('ROLE_MODERATOR');
    }
}