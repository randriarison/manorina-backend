<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Uid\Uuid;

final class ExcludeCategoriesFilter extends AbstractFilter
{
 
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if ($property !== 'excludeCategories') {
            return;
        }

        if (empty($value)) {
            return;
        }
        // Handle both array and comma-separated string values
        $categoryIds = [];
        if (is_array($value)) {
            $categoryIds = $value;
        } else {
            // Handle comma-separated string (split and clean up)
            $categoryIds = array_map('trim', explode(',', $value));
        }
        
        // Clean up the IDs (remove quotes and empty values)
        $categoryIds = array_map(function($id) {
            return trim($id, '"\''); // Remove quotes
        }, $categoryIds);
        
        $categoryIds = array_filter($categoryIds); // Remove empty values
        
        if (empty($categoryIds)) {
            return;
        }

        // Convert UUID strings to binary format for database comparison
        $binaryIds = [];
        foreach ($categoryIds as $categoryId) {
            try {
                $binaryIds[] = Uuid::fromString($categoryId)->toBinary();
            } catch (\Exception $e) {
                // Skip invalid UUID strings
                continue;
            }
        }
        
        if (empty($binaryIds)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName('excludeCategories');
        
        // Create a subquery to find articles that have any of the excluded categories
        $subQueryBuilder = $queryBuilder->getEntityManager()->createQueryBuilder();
        $subQueryBuilder
            ->select('a_sub.id')
            ->from($resourceClass, 'a_sub')
            ->join('a_sub.category', 'c_sub')
            ->where('c_sub.id IN (:' . $parameterName . ')');

        // Exclude articles that are found in the subquery
        $queryBuilder
            ->andWhere($queryBuilder->expr()->notIn(
                $rootAlias . '.id',
                $subQueryBuilder->getDQL()
            ))
            ->setParameter($parameterName, $binaryIds);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'excludeCategories' => [
                'property' => 'excludeCategories',
                'type' => Type::BUILTIN_TYPE_ARRAY,
                'required' => false,
                'description' => 'Exclude articles that belong to any of the specified category IDs. Can be a single ID or an array of IDs.',
                'openapi' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'format' => 'uuid'
                    ],
                    'example' => ['01234567-89ab-cdef-0123-456789abcdef', '01234567-89ab-cdef-0123-456789abcde0']
                ]
            ]
        ];
    }
}