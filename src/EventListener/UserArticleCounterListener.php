<?php

namespace App\EventListener;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class UserArticleCounterListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();
        
        if ($entity instanceof Article) {
            $this->updateUserCounters($entity->getAuthor());
        }
    }

    public function postUpdate(PostUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        
        if ($entity instanceof Article) {
            $this->updateUserCounters($entity->getAuthor());
        }
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $entity = $event->getObject();
        
        if ($entity instanceof Article) {
            $this->updateUserCounters($entity->getAuthor());
        }
    }

    private function updateUserCounters(User $user): void
    {
        // Count draft articles
        $draftCount = $this->entityManager->getRepository(Article::class)->count([
            'author' => $user,
            'status' => ArticleStatus::DRAFT
        ]);

        // Count published articles
        $publishedCount = $this->entityManager->getRepository(Article::class)->count([
            'author' => $user,
            'status' => ArticleStatus::PUBLISHED
        ]);

        // Update user counters
        $user->setNbDraftArticles($draftCount);
        $user->setNbPublishedArticles($publishedCount);

        // Persist and flush the user changes
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}