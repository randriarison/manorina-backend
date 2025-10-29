<?php

namespace App\EventSubscriber;

use App\Entity\Traits\BlameableTrait;
use App\Entity\Traits\SluggableTrait;
use App\Entity\Traits\TimestampableTrait;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class EntityLifecycleSubscriber implements EventSubscriber
{
    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
        private ?SluggerInterface $slugger = null
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
                throw new \RuntimeException('Cannot create entity with BlameableTrait: no authenticated user found');
            }
            // Always set the author from the current authenticated user
            $entity->setAuthor($currentUser);
        }

        // Handle SluggableTrait
        if (method_exists($entity, 'updateSlug') && method_exists($entity, 'getTitle')) {
            $title = $entity->getTitle();
            if ($title && $this->slugger) {
                $slug = $this->slugger->slug($title)->lower();
                $entity->updateSlug($title, (string) $slug);
            }
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

        // Handle SluggableTrait - regenerate slug if title changed
        if (method_exists($entity, 'forceUpdateSlug') && 
            method_exists($entity, 'getTitle') && 
            $args->hasChangedField('title')) {
            $newTitle = $args->getNewValue('title');
            if ($newTitle && $this->slugger) {
                $slug = $this->slugger->slug($newTitle)->lower();
                $entity->forceUpdateSlug((string) $slug);
            }
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
}