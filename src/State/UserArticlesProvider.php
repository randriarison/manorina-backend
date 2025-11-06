<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use App\Repository\ArticleRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * State provider for fetching articles belonging to the authenticated user
 */
class UserArticlesProvider implements ProviderInterface
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private Security $security,
        private RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the authenticated user
        $user = $this->security->getUser();
        
        if (!$user) {
            throw new \RuntimeException('User not authenticated');
        }

        // Get request parameters for pagination and filtering
        $request = $this->requestStack->getCurrentRequest();
        $page = 1;
        $limit = 30;
        
        if ($request) {
            $page = $request->query->getInt('page', 1);
            $limit = $request->query->getInt('limit', 30);
        }
        
        $offset = ($page - 1) * $limit;

        // Find articles authored by the current user
        $articles = $this->articleRepository->findBy(
            ['author' => $user],
            ['updatedAt' => 'DESC', 'descendantCount' => 'DESC'], // Order by update date DESC, then descendant count DESC
            $limit,
            $offset
        );

        return $articles;
    }
}