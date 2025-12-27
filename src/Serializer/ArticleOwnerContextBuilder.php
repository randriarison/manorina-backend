<?php

namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Entity\Article;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class ArticleOwnerContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
        private Security $security
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        // Only modify context for normalization (reading) of Article resources
        if (!$normalization) {
            return $context;
        }

        // Check if we're working with Article entity
        $resourceClass = $context['resource_class'] ?? null;
        if ($resourceClass !== Article::class) {
            return $context;
        }

        // Store current user in context and add max depth to prevent recursion
        $currentUser = $this->security->getUser();
        if ($currentUser) {
            $context['current_user_for_ownership'] = $currentUser;
        }
        
        // Set max depth for Article to prevent infinite recursion
        $context['enable_max_depth'] = true;

        return $context;
    }
}
