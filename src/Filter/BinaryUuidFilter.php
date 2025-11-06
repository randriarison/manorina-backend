<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Uid\Uuid;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;


/**
 * Custom filter to convert string UUIDs to binary for filtering.
 */
final class BinaryUuidFilter extends AbstractFilter
{
    
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ):void {
        // Check if the property should be filtered
        if ($property === 'page' || !$this->isPropertyEnabled($property, $resourceClass) || !Uuid::isValid($value)) {
            return;
        }
        $rootAlias = $queryBuilder->getRootAliases()[0];
        //split property if needed to generate join syntax
        $propertyArray = explode('.', $property);
        if(count($propertyArray) > 0) {
            $previousProp = '';
            foreach($propertyArray as $prop) {
                //Generate a unique parameter name to avoid collisions with other filters
                $parameterName = $queryNameGenerator->generateParameterName($prop);
                $currentProp = !empty($previousProp) ? $previousProp .".". $prop : $rootAlias .".".$prop;
                $queryBuilder->join($currentProp , $parameterName);
                $previousProp = $parameterName;
            }
            $queryBuilder->andWhere("$parameterName.id = :objectId");
            $queryBuilder->setParameter('objectId', Uuid::fromString($value)->toBinary());
        } 
        /*else {
            $queryBuilder
            ->andWhere("$rootAlias.$property.id = :objectId")
            ->setParameter('objectId', Uuid::fromString($value));
        }*/
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];
        if ($resourceClass === "App\Entity\Article") {
            $description['category'] = [
                'property' => 'id',
                'type' => Type::string(),
                'required' => false,
                'description' => 'Filter articles by category id',
            ];
             $description['author'] = [
                'property' => 'id',
                'type' => Type::string(),
                'required' => false,
                'description' => 'Filter articles by author id',
            ];
            $description['parentArticle'] = [
                'property' => 'id',
                'type' => Type::string(),
                'required' => false,
                'description' => 'Filter articles by parent id',
            ];
            
        } elseif ($resourceClass === "App\Entity\Transaction") {
            $description['transactionProducts.salePrice.onSale.produit'] = [
                'property' => 'id',
                'type' => Type::string(),
                'required' => false,
                'description' => 'Filter transactions by product UUID',
            ];
        }

        return $description;
    }
}
