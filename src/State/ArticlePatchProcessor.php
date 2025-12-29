<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Custom processor for Article PATCH operations to handle draft content timestamp
 */
class ArticlePatchProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
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
            
            // Force slug update for draft articles without content when title changes
            if ($originalData) {
                $isDraft = $data->getStatus() === ArticleStatus::DRAFT;
                $hasNoContent = empty($data->getContent()) && empty($data->getContentMg());
                $titleChanged = (isset($originalData['title']) && $data->getTitle() !== $originalData['title'])
                    || (isset($originalData['titleMg']) && $data->getTitleMg() !== $originalData['titleMg']);
                
                if ($isDraft && $hasNoContent && $titleChanged) {
                    // Determine the source text for slug generation
                    $sourceText = $data->getTitle() ?: $data->getTitleMg();
                    
                    if ($sourceText) {
                        $baseSlug = $this->slugger->slug($sourceText)->lower();
                        $uniqueSlug = $this->generateUniqueSlug((string) $baseSlug, Article::class, $data->getId());
                        $data->forceUpdateSlug($uniqueSlug);
                    }
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
    
    /**
     * Generate a unique slug by appending a counter if needed
     */
    private function generateUniqueSlug(string $baseSlug, string $entityClass, mixed $currentEntityId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;
        
        while ($this->slugExists($slug, $entityClass, $currentEntityId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if a slug already exists in the database
     */
    private function slugExists(string $slug, string $entityClass, mixed $currentEntityId = null): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from($entityClass, 'e')
            ->where('e.slug = :slug')
            ->setParameter('slug', $slug);
        
        // Exclude current entity from check
        if ($currentEntityId !== null) {
            $qb->andWhere('e.id != :id')
                ->setParameter('id', $currentEntityId);
        }
        
        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
