<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty(trim($query))) {
            return $this->json([
                'articles' => [],
                'categories' => []
            ]);
        }

        // Split query into words for better matching
        $searchTerms = array_filter(array_map('trim', explode(' ', $query)));
        
        $articles = $this->searchArticles($searchTerms);
        $categories = $this->searchCategories($searchTerms);

        // Serialize the results
        $articlesData = json_decode($this->serializer->serialize($articles, 'json', [
            'groups' => ['article:read']
        ]), true);

        $categoriesData = json_decode($this->serializer->serialize($categories, 'json', [
            'groups' => ['category:read']
        ]), true);

        return $this->json([
            'articles' => $articlesData,
            'categories' => $categoriesData,
            'total' => count($articles) + count($categories)
        ]);
    }

    private function searchArticles(array $searchTerms): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
           ->from(Article::class, 'a')
           ->leftJoin('a.category', 'c')
           ->where('a.status != :archivedStatus AND a.status != :draftStatus')
           ->setParameter('archivedStatus', 'archived')
           ->setParameter('draftStatus', 'draft');

        $conditions = [];
        $parameters = [];

        foreach ($searchTerms as $index => $term) {
            $paramName = 'term' . $index;
            $conditions[] = $qb->expr()->orX(
                $qb->expr()->like('LOWER(a.title)', ':' . $paramName),
                $qb->expr()->like('LOWER(a.titleMg)', ':' . $paramName),
                $qb->expr()->like('LOWER(c.name)', ':' . $paramName)
            );
            $parameters[$paramName] = '%' . strtolower($term) . '%';
        }

        if (!empty($conditions)) {
            $qb->andWhere($qb->expr()->andX(...$conditions));
            foreach ($parameters as $param => $value) {
                $qb->setParameter($param, $value);
            }
        }

        // Add ordering and limit
        $qb->orderBy('a.updatedAt', 'DESC')
           ->setMaxResults(50);

        return $qb->getQuery()->getResult();
    }

    private function searchCategories(array $searchTerms): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c')
           ->from(Category::class, 'c')
           ->where('c.status != :draftStatus')
           ->setParameter('draftStatus', 'draft');

        $conditions = [];
        $parameters = [];

        foreach ($searchTerms as $index => $term) {
            $paramName = 'cat_term' . $index;
            $conditions[] = $qb->expr()->orX(
                $qb->expr()->like('LOWER(c.name)', ':' . $paramName),
                $qb->expr()->like('LOWER(c.nameMg)', ':' . $paramName)
            );
            $parameters[$paramName] = '%' . strtolower($term) . '%';
        }

        if (!empty($conditions)) {
            $qb->andWhere($qb->expr()->andX(...$conditions));
            foreach ($parameters as $param => $value) {
                $qb->setParameter($param, $value);
            }
        }

        // Add ordering and limit
        $qb->orderBy('c.name', 'ASC')
           ->setMaxResults(50);

        return $qb->getQuery()->getResult();
    }
}