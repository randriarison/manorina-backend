<?php

namespace App\State;

use ApiPlatform\Metadata\Exception\InvalidUriVariableException;
use ApiPlatform\Metadata\UriVariableTransformerInterface;
use App\Entity\Article;
use App\Repository\ArticleRepository;

/**
 * Transforms slug URI variables to Article entities
 */
class ArticleSlugUriVariableTransformer implements UriVariableTransformerInterface
{
    public function __construct(
        private ArticleRepository $articleRepository
    ) {}

    public function transform(mixed $value, array $types, array $context = []): mixed
    {
        // Check if this is an Article transformation and we have a slug value
        if (!isset($types[Article::class]) || !is_string($value)) {
            return $value;
        }

        // Find article by slug
        $article = $this->articleRepository->findOneBy(['slug' => $value]);
        
        if (!$article) {
            throw new InvalidUriVariableException(sprintf('Article with slug "%s" not found', $value));
        }

        return $article;
    }

    public function supportsTransformation(mixed $value, array $types, array $context = []): bool
    {
        // Support transformation when we have an Article type and a string value (slug)
        return isset($types[Article::class]) && is_string($value);
    }
}