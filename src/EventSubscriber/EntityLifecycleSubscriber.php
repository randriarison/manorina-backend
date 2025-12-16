<?php

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\SluggableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use App\Enum\ArticleStatus;
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
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
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
            $sourceText = $this->getSlugSourceText($entity);
            
            if ($sourceText && $this->slugger && $this->entityManager) {
                $baseSlug = $this->slugger->slug($sourceText)->lower();
                $uniqueSlug = $this->generateUniqueSlug((string) $baseSlug, get_class($entity));
                $entity->updateSlug($sourceText, $uniqueSlug);
            } elseif (!$sourceText && ($entity instanceof Article || $entity instanceof Category) && $this->entityManager) {
                // Fallback to ID for Articles and Categories when no title/name is available
                $idSlug = (string) $entity->getId();
                // Use the ID directly as the slug without further slugging since UUIDs are already URL-safe
                $uniqueSlug = $this->generateUniqueSlug($idSlug, get_class($entity));
                $entity->updateSlug($idSlug, $uniqueSlug);
            }
        }

        // Handle Article hierarchy logic
        if ($entity instanceof Article) {
            $this->handleArticleHierarchy($entity);
            // Synchronize status of child articles with parent
            $this->synchronizeChildArticlesStatus($entity);
        }

        // Handle Category hierarchy logic
        if ($entity instanceof Category) {
            $this->handleCategoryHierarchy($entity);
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
            
            // For Articles, check for title or titleMg changes
            if ($entity instanceof Article) {
                if ($args->hasChangedField('title') || $args->hasChangedField('titleMg')) {
                    $sourceText = $this->getSlugSourceText($entity);
                    $hasChanged = true;
                }
            } elseif ($entity instanceof Category) {
                // For Categories, check for name or nameMg changes
                if ($args->hasChangedField('name') || $args->hasChangedField('nameMg')) {
                    $sourceText = $this->getSlugSourceText($entity);
                    $hasChanged = true;
                }
            } elseif (method_exists($entity, 'getTitle') && $args->hasChangedField('title')) {
                $sourceText = $args->getNewValue('title');
                $hasChanged = true;
            } elseif (method_exists($entity, 'getName') && $args->hasChangedField('name')) {
                $sourceText = $args->getNewValue('name');
                $hasChanged = true;
            }
            
            if ($hasChanged && $this->slugger && $this->entityManager) {
                if ($sourceText) {
                    $baseSlug = $this->slugger->slug($sourceText)->lower();
                    $uniqueSlug = $this->generateUniqueSlug((string) $baseSlug, get_class($entity));
                    $entity->forceUpdateSlug($uniqueSlug);
                } elseif ($entity instanceof Article || $entity instanceof Category) {
                    // Fallback to ID for Articles and Categories when no title/name is available
                    $idSlug = (string) $entity->getId();
                    $uniqueSlug = $this->generateUniqueSlug($idSlug, get_class($entity));
                    $entity->forceUpdateSlug($uniqueSlug);
                }
            }
        }

        // Handle Article hierarchy changes
        if ($entity instanceof Article && $args->hasChangedField('parentArticle')) {
            $this->handleArticleHierarchyUpdate($entity, $args);
        }

        // Handle Article status changes - synchronize with children
        if ($entity instanceof Article && $args->hasChangedField('status')) {
            $this->synchronizeChildArticlesStatus($entity);
            $this->handleStatusChange($entity, $args);
        }

        // Handle Category status changes (if Category has status field)
        if ($entity instanceof Category && $args->hasChangedField('status')) {
            $this->handleStatusChange($entity, $args);
        }

        // Handle Category hierarchy changes
        if ($entity instanceof Category && $args->hasChangedField('parent')) {
            $this->handleCategoryHierarchyUpdate($entity, $args);
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

    private function getSlugSourceText($entity): ?string
    {
        // For Articles, prioritize title, then titleMg
        if ($entity instanceof Article) {
            $title = $entity->getTitle();
            if ($title && trim($title)) {
                return $title;
            }
            
            $titleMg = method_exists($entity, 'getTitleMg') ? $entity->getTitleMg() : null;
            if ($titleMg && trim($titleMg)) {
                return $titleMg;
            }
            
            // Return null if no valid title is found, will fallback to ID
            return null;
        }
        
        // For Categories, prioritize name, then nameMg
        if ($entity instanceof Category) {
            $name = $entity->getName();
            if ($name && trim($name)) {
                return $name;
            }
            
            $nameMg = method_exists($entity, 'getNameMg') ? $entity->getNameMg() : null;
            if ($nameMg && trim($nameMg)) {
                return $nameMg;
            }
            
            // Return null if no valid name is found, will fallback to ID
            return null;
        }
        
        // For other entities, use existing logic
        if (method_exists($entity, 'getTitle')) {
            return $entity->getTitle();
        } elseif (method_exists($entity, 'getName')) {
            return $entity->getName();
        }
        
        return null;
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
        // Increment parent's direct children count (only for immediate parent)
        $parent->setChildrenCount(($parent->getChildrenCount() ?? 0) + 1);
        
        // Increment parent's descendant count by the increment value
        $parent->setDescendantCount(($parent->getDescendantCount() ?? 0) + $descendantIncrement);

        // Propagate the descendant count up the hierarchy (but NOT the children count)
        $grandParent = $parent->getParentArticle();
        if ($grandParent !== null) {
            // Only propagate descendant count, not children count
            $this->propagateDescendantCount($grandParent, $descendantIncrement);
        }
    }

    private function propagateDescendantCount(Article $ancestor, int $descendantIncrement): void
    {
        // Only increment descendant count for ancestors (not children count)
        $ancestor->setDescendantCount(($ancestor->getDescendantCount() ?? 0) + $descendantIncrement);

        // Continue propagating up the hierarchy
        $parent = $ancestor->getParentArticle();
        if ($parent !== null) {
            $this->propagateDescendantCount($parent, $descendantIncrement);
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
        // Decrease parent's direct children count (only for immediate parent)
        $parent->setChildrenCount(max(0, ($parent->getChildrenCount() ?? 0) - 1));
        
        // Decrease parent's descendant count by the decrement value
        $parent->setDescendantCount(max(0, ($parent->getDescendantCount() ?? 0) - $descendantDecrement));

        // Propagate the descendant count decrease up the hierarchy (but NOT the children count)
        $grandParent = $parent->getParentArticle();
        if ($grandParent !== null) {
            // Only propagate descendant count decrease, not children count decrease
            $this->propagateDescendantCountDecrease($grandParent, $descendantDecrement);
        }
    }

    private function propagateDescendantCountDecrease(Article $ancestor, int $descendantDecrement): void
    {
        // Only decrease descendant count for ancestors (not children count)
        $ancestor->setDescendantCount(max(0, ($ancestor->getDescendantCount() ?? 0) - $descendantDecrement));

        // Continue propagating up the hierarchy
        $parent = $ancestor->getParentArticle();
        if ($parent !== null) {
            $this->propagateDescendantCountDecrease($parent, $descendantDecrement);
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

    private function handleCategoryHierarchy(Category $category): void
    {
        // Set level based on parent
        if ($category->getParent() !== null) {
            $category->setLevel($category->getParent()->getLevel() + 1);
        } else {
            $category->setLevel(0); // Root category
        }
    }

    private function handleCategoryHierarchyUpdate(Category $category, PreUpdateEventArgs $args): void
    {
        $oldParent = $args->getOldValue('parent');
        $newParent = $args->getNewValue('parent');

        // Update level based on new parent
        if ($newParent !== null) {
            $category->setLevel($newParent->getLevel() + 1);
        } else {
            $category->setLevel(0); // Root category
        }

        // Update all children levels recursively
        $this->updateCategoryChildrenHierarchy($category);
    }

    private function updateCategoryChildrenHierarchy(Category $category): void
    {
        foreach ($category->getChildren() as $child) {
            $child->setLevel($category->getLevel() + 1);
            
            // Recursively update all descendants
            $this->updateCategoryChildrenHierarchy($child);
        }
    }

    /**
     * Synchronize the status of all child articles with their parent article
     */
    private function synchronizeChildArticlesStatus(Article $parentArticle): void
    {
        $parentStatus = $parentArticle->getStatus();
        
        if ($parentStatus === null) {
            return; // No status to synchronize
        }

        foreach ($parentArticle->getChildArticles() as $childArticle) {
            // Update child status to match parent
            $childArticle->setStatus($parentStatus);
            
            // Recursively update grandchildren if any
            $this->synchronizeChildArticlesStatus($childArticle);
        }
    }

    /**
     * Handle status changes to track publication and archival dates/users
     */
    private function handleStatusChange($entity, PreUpdateEventArgs $args): void
    {
        $currentUser = $this->getCurrentUser();
        $newStatus = $args->getNewValue('status');
        $oldStatus = $args->getOldValue('status');

        // Check if entity has the required methods (uses TimestampableTrait and BlameableTrait)
        if (!method_exists($entity, 'setPublishedAt') || !method_exists($entity, 'setPublishedBy') ||
            !method_exists($entity, 'setArchivedAt') || !method_exists($entity, 'setArchivedBy')) {
            return;
        }

        // Get status values safely (handle both enum objects and strings)
        $newStatusValue = $this->getStatusValue($newStatus);
        $oldStatusValue = $this->getStatusValue($oldStatus);

        // Handle status change to PUBLISHED
        if ($newStatusValue === 'published' && $oldStatusValue !== 'published') {
            $entity->setPublishedAt(new \DateTimeImmutable());
            if ($currentUser) {
                $entity->setPublishedBy($currentUser);
            }
            
            // Clear archived fields if coming from archived status
            if ($oldStatusValue === 'archived') {
                $entity->setArchivedAt(null);
                $entity->setArchivedBy(null);
            }
        }

        // Handle status change to ARCHIVED
        if ($newStatusValue === 'archived' && $oldStatusValue !== 'archived') {
            $entity->setArchivedAt(new \DateTimeImmutable());
            if ($currentUser) {
                $entity->setArchivedBy($currentUser);
            }
        }

        // Handle status change from PUBLISHED/ARCHIVED to DRAFT
        if ($newStatusValue === 'draft') {
            if ($oldStatusValue === 'published') {
                // Clear published fields when moving to draft
                $entity->setPublishedAt(null);
                $entity->setPublishedBy(null);
            }
            if ($oldStatusValue === 'archived') {
                // Clear archived fields when moving to draft
                $entity->setArchivedAt(null);
                $entity->setArchivedBy(null);
            }
        }
    }

    /**
     * Safely get the status value from either an enum object or string
     */
    private function getStatusValue($status): ?string
    {
        if ($status === null) {
            return null;
        }
        
        // If it's an enum object, get the value property
        if (is_object($status) && property_exists($status, 'value')) {
            return $status->value;
        }
        
        // If it's already a string, return it
        if (is_string($status)) {
            return $status;
        }
        
        return null;
    }

    /**
     * Handle entity persistence - update user counters when articles are created
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Update user article counters when an article is persisted
        if ($entity instanceof Article) {
            $this->updateUserArticleCounters($entity->getAuthor());
        }
    }

    /**
     * Handle entity updates - update user counters when articles are modified
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        // Update user article counters when an article is updated
        if ($entity instanceof Article) {
            $this->updateUserArticleCounters($entity->getAuthor());
        }
    }

    /**
     * Handle entity removal - update user counters when articles are deleted
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        // Update user article counters when an article is removed
        if ($entity instanceof Article) {
            $this->updateUserArticleCounters($entity->getAuthor());
        }
    }

    /**
     * Update the user's article counters based on their articles' statuses
     */
    private function updateUserArticleCounters(User $user): void
    {
        if (!$this->entityManager) {
            return;
        }

        // Calculate the counts directly from the database to ensure accuracy
        $draftCount = $this->countUserArticlesByStatus($user, ArticleStatus::DRAFT);
        $publishedCount = $this->countUserArticlesByStatus($user, ArticleStatus::PUBLISHED);

        // Update the user's counters
        $user->setNbDraftArticles($draftCount);
        $user->setNbPublishedArticles($publishedCount);

        // Persist the user entity to save the updated counters
        $this->entityManager->persist($user);
    }

    /**
     * Count articles for a user by status
     */
    private function countUserArticlesByStatus(User $user, ArticleStatus $status): int
    {
        if (!$this->entityManager) {
            return 0;
        }

        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(a.id)')
                ->from(Article::class, 'a')
                ->where('a.author = :user')
                ->andWhere('a.status = :status')
                ->setParameter('user', $user)
                ->setParameter('status', $status);

            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (\Exception $e) {
            // In case of error, return 0 to avoid breaking the application
            return 0;
        }
    }
}