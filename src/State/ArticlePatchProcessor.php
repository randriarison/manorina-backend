<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Custom processor for Article PATCH operations to handle draft content timestamp
 */
class ArticlePatchProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Article) {
            // Get the original entity to compare changes
            $uow = $this->entityManager->getUnitOfWork();
            $uow->computeChangeSets();
            $changeSet = $uow->getEntityChangeSet($data);
            
            // Check if any draft content fields have changed
            if (isset($changeSet['draftContent']) || 
                isset($changeSet['draftContentMg']) || 
                isset($changeSet['draftContentEn'])) {
                $data->setSaveDraftTime(new \DateTime());
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
