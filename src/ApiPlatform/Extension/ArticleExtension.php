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

final class ArticleExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private Security $security) {}

    private function addWhere(QueryBuilder $qb, string $resourceClass): void
    {
        if (Article::class !== $resourceClass) {
            return;
        }

        $user = $this->security->getUser();

        // alias root entity
        $rootAlias = $qb->getRootAliases()[0];

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
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }
}
