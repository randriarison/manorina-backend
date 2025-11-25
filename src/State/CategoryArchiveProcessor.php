<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Category;
use App\Enum\CategoryStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CategoryArchiveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Category
    {
        /** @var Category $category */
        $category = $data;
        
        // Set status to archived
        $category->setStatus(CategoryStatus::ARCHIVED);
        
        // Set archived date and user (handled by EntityLifecycleSubscriber)
        $this->entityManager->flush();
        
        return $category;
    }
}