<?php

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\SluggableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class EntityLifecycleSubscriber implements EventSubscriber
{
    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
        private ?SluggerInterface $slugger = null,
        private ?EntityManagerInterface $entityManager = null
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Handle TimestampableTrait
        if (method_exists($entity, 'initializeTimestamps')) {
            $entity->initializeTimestamps();
        }

        // Handle BlameableTrait for new entities
        if (method_exists($entity, 'setAuthor')) {
            $currentUser = $this->getCurrentUser();
            if (!$currentUser) {
                // Check if author is already set (e.g., in console commands)
                if (method_exists($entity, 'getAuthor') && $entity->getAuthor() === null) {
                    throw new \RuntimeException('Cannot create entity with BlameableTrait: no authenticated user found');
                }
                // If author is already set, don't override it
            } else {
                // Always set the author from the current authenticated user when available
                $entity->setAuthor($currentUser);
            }
        }

        // Handle SluggableTrait
        if (method_exists($entity, 'updateSlug')) {
            $sourceText = null;
            if (method_exists($entity, 'getTitle')) {
                $sourceText = $entity->getTitle();
            } elseif (method_exists($entity, 'getName')) {
                $sourceText = $entity->getName();
            }
            
            if ($sourceText && $this->slugger && $this->entityManager) {
                $baseSlug = $this->slugger->slug($sourceText)->lower();
                $uniqueSlug = $this->generateUniqueSlug((string) $baseSlug, get_class($entity));
                $entity->updateSlug($sourceText, $uniqueSlug);
            }
        }

        // Handle Article hierarchy logic
        if ($entity instanceof Article) {
            $this->handleArticleHierarchy($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        // Handle TimestampableTrait
        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }

        // Handle BlameableTrait for updates
        if (method_exists($entity, 'setUpdatedBy')) {
            $currentUser = $this->getCurrentUser();
            if ($currentUser) {
                $entity->setUpdatedBy($currentUser);
            }
        }

        // Handle SluggableTrait - regenerate slug if title or name changed
        if (method_exists($entity, 'forceUpdateSlug')) {
            $sourceText = null;
            $hasChanged = false;
            
            if (method_exists($entity, 'getTitle') && $args->hasChangedField('title')) {
                $sourceText = $args->getNewValue('title');
                $hasChanged = true;
            } elseif (method_exists($entity, 'getName') && $args->hasChangedField('name')) {
                $sourceText = $args->getNewValue('name');
                $hasChanged = true;
            }
            
            if ($hasChanged && $sourceText && $this->slugger && $this->entityManager) {
                $baseSlug = $this->slugger->slug($sourceText)->lower();
                $uniqueSlug = $this->generateUniqueSlug((string) $baseSlug, get_class($entity));
                $entity->forceUpdateSlug($uniqueSlug);
            }
        }

        // Handle Article hierarchy changes
        if ($entity instanceof Article && $args->hasChangedField('parentArticle')) {
            $this->handleArticleHierarchyUpdate($entity, $args);
        }
    }

    private function getCurrentUser(): ?User
    {
        if (!$this->tokenStorage) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        return $user instanceof User ? $user : null;
    }

    private function generateUniqueSlug(string $baseSlug, string $entityClass): string
    {
        if (!$this->entityManager) {
            return $baseSlug;
        }

        $repository = $this->entityManager->getRepository($entityClass);
        $slug = $baseSlug;
        $counter = 1;

        // Check if slug already exists and increment counter until we find a unique one
        while ($this->slugExists($repository, $slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists($repository, string $slug): bool
    {
        try {
            $existing = $repository->findOneBy(['slug' => $slug]);
            return $existing !== null;
        } catch (\Exception $e) {
            // If repository doesn't have slug field, return false
            return false;
        }
    }

    private function handleArticleHierarchy(Article $article): void
    {
        $parent = $article->getParentArticle();

        if ($parent === null) {
            // This is a root article
            $article->setLevel(0);
            $article->setRootArticle(null); // Root articles don't have a root article reference
            $article->setDescendantCount(0); // New articles start with 0 descendants
        } else {
            // This article has a parent
            $article->setLevel($parent->getLevel() + 1);
            $article->setRootArticle($parent->getRootArticle() ?? $parent);
            $article->setDescendantCount(0); // New articles start with 0 descendants

            // Update parent's counters
            $this->updateParentCounters($parent, 1);
        }

        // Initialize childrenCount to 0 for new articles
        $article->setChildrenCount(0);
    }

    private function updateParentCounters(Article $parent, int $descendantIncrement): void
    {
        // Increment parent's children count
        $parent->setChildrenCount(($parent->getChildrenCount() ?? 0) + 1);
        
        // Increment parent's descendant count by the increment value
        $parent->setDescendantCount(($parent->getDescendantCount() ?? 0) + $descendantIncrement);

        // Propagate the descendant count up the hierarchy
        $grandParent = $parent->getParentArticle();
        if ($grandParent !== null) {
            $this->updateParentCounters($grandParent, $descendantIncrement);
        }
    }

    private function handleArticleHierarchyUpdate(Article $article, PreUpdateEventArgs $args): void
    {
        $oldParent = $args->getOldValue('parentArticle');
        $newParent = $args->getNewValue('parentArticle');

        // If parent changed, we need to update counters for both old and new parents
        if ($oldParent !== $newParent) {
            // Decrease counters for old parent hierarchy
            if ($oldParent instanceof Article) {
                $this->decreaseParentCounters($oldParent, $article->getDescendantCount() + 1);
            }

            // Update article hierarchy based on new parent
            if ($newParent === null) {
                // Article is becoming a root article
                $article->setLevel(0);
                $article->setRootArticle(null);
            } else {
                // Article is getting a new parent
                $article->setLevel($newParent->getLevel() + 1);
                $article->setRootArticle($newParent->getRootArticle() ?? $newParent);
                
                // Update new parent's counters
                $this->updateParentCounters($newParent, $article->getDescendantCount() + 1);
            }

            // Update all child articles to reflect the new hierarchy
            $this->updateChildrenHierarchy($article);
        }
    }

    private function decreaseParentCounters(Article $parent, int $descendantDecrement): void
    {
        // Decrease parent's children count
        $parent->setChildrenCount(max(0, ($parent->getChildrenCount() ?? 0) - 1));
        
        // Decrease parent's descendant count by the decrement value
        $parent->setDescendantCount(max(0, ($parent->getDescendantCount() ?? 0) - $descendantDecrement));

        // Propagate the descendant count decrease up the hierarchy
        $grandParent = $parent->getParentArticle();
        if ($grandParent !== null) {
            $this->decreaseParentCounters($grandParent, $descendantDecrement);
        }
    }

    private function updateChildrenHierarchy(Article $article): void
    {
        foreach ($article->getChildArticles() as $child) {
            $child->setLevel($article->getLevel() + 1);
            $child->setRootArticle($article->getRootArticle() ?? $article);
            
            // Recursively update all descendants
            $this->updateChildrenHierarchy($child);
        }
    }
}