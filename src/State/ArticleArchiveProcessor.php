<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ArticleArchiveProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Article
    {
        /** @var Article $article */
        $article = $data;
        
        // Set status to archived
        $article->setStatus(ArticleStatus::ARCHIVED);
        
        // Set archived date and user (handled by EntityLifecycleSubscriber)
        $this->entityManager->flush();
        
        return $article;
    }
}