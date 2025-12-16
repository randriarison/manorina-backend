<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Article processor to ensure author is set from authenticated user
 */
class ArticleProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Article) {
            // Ensure author is set from authenticated user for new articles
            $user = $this->security->getUser();
            if ($user && method_exists($data, 'getAuthor') && method_exists($data, 'setAuthor')) {
                // Only set author if it's not already set (for new entities)
                try {
                    $currentAuthor = $data->getAuthor();
                    if (!$currentAuthor) {
                        $data->setAuthor($user);
                    }
                } catch (\Error $e) {
                    // Author is not set yet (new entity), so set it
                    $data->setAuthor($user);
                }
            }

            // Handle article hierarchy if this is a new article with a parent
            // This is needed because EntityLifecycleSubscriber might not be working properly
            if (!$data->getId() && $data->getParentArticle()) {
                $this->setupArticleHierarchy($data);
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function setupArticleHierarchy(Article $article): void
    {
        $parent = $article->getParentArticle();

        if ($parent === null) {
            // This is a root article
            $article->setLevel(0);
            $article->setRootArticle(null);
            $article->setDescendantCount(0);
        } else {
            // This article has a parent
            $article->setLevel($parent->getLevel() + 1);
            $article->setRootArticle($parent->getRootArticle() ?? $parent);
            $article->setDescendantCount(0);

            // Update parent's counters
            $this->updateParentCounters($parent, 1);
        }

        // Initialize childrenCount to 0 for new articles
        if ($article->getChildrenCount() === null) {
            $article->setChildrenCount(0);
        }
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
}