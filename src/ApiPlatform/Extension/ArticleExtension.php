<?php
namespace App\ApiPlatform\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Article;
use ApiPlatform\Metadata\Operation;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use Symfony\Component\HttpFoundation\RequestStack;

final class ArticleExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack
    ) {}

    private function addWhere(QueryBuilder $qb, string $resourceClass, ?Operation $operation = null): void
    {
        if (Article::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();
        $rootAlias = $qb->getRootAliases()[0];

        // Check if this is the main articles collection (not the only-mine endpoint)
        $isMainCollection = $operation && $operation instanceof \ApiPlatform\Metadata\GetCollection;
        
        if ($isMainCollection) {
            $request = $this->requestStack->getCurrentRequest();
            $isOnlyMineEndpoint = $request && str_contains($request->getPathInfo(), '/only-mine');
            $hasParentFilter = $request && $request->query->has('parentArticle');
            
            // Only apply type filtering to main collection, not to only-mine endpoint
            // If no parentArticle filter is specified, exclude comments and answers from main listing
            if (!$isOnlyMineEndpoint && !$hasParentFilter) {
                $qb->andWhere(sprintf('%s.articleType NOT IN (:excluded_types)', $rootAlias))
                   ->setParameter('excluded_types', [ArticleType::COMMENT->value, ArticleType::ANSWER->value]);
            }
        }

        // Apply status-based filtering
        if (!$user) {
            // Visiteur non connecté : ne voir que les articles publiés
            $qb->andWhere(sprintf('%s.status = :published', $rootAlias))
               ->setParameter('published', ArticleStatus::PUBLISHED);
            return;
        }

        // Utilisateur connecté : voir tous ses articles + ceux publiés
        $qb->join(sprintf('%s.author', $rootAlias), 'author')
        ->andWhere(sprintf('(%s.status = :published OR author.email = :current_user_id)', $rootAlias, $rootAlias))
           ->setParameter('published', ArticleStatus::PUBLISHED)
           ->setParameter('current_user_id', $user->getUserIdentifier());
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass, $operation);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass, $operation);
    }
}
