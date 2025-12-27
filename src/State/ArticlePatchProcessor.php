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
            // Get the original entity data from UnitOfWork before the changes
            $uow = $this->entityManager->getUnitOfWork();
            $originalData = $uow->getOriginalEntityData($data);
            
            // Check if any draft content fields have changed by comparing with original
            $draftContentChanged = false;
            
            if ($originalData) {
                if (isset($originalData['draftContent']) && $data->getDraftContent() !== $originalData['draftContent']) {
                    $draftContentChanged = true;
                }
                if (isset($originalData['draftContentMg']) && $data->getDraftContentMg() !== $originalData['draftContentMg']) {
                    $draftContentChanged = true;
                }
                if (isset($originalData['draftContentEn']) && $data->getDraftContentEn() !== $originalData['draftContentEn']) {
                    $draftContentChanged = true;
                }
                
                // Also handle case where field was null before and now has a value
                if (!isset($originalData['draftContent']) && $data->getDraftContent() !== null) {
                    $draftContentChanged = true;
                }
                if (!isset($originalData['draftContentMg']) && $data->getDraftContentMg() !== null) {
                    $draftContentChanged = true;
                }
                if (!isset($originalData['draftContentEn']) && $data->getDraftContentEn() !== null) {
                    $draftContentChanged = true;
                }
            }
            
            if ($draftContentChanged) {
                $data->setSaveDraftTime(new \DateTime());
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
