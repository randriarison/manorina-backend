<?php

namespace App\Serializer;

use App\Entity\Article;
use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CategoryDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'CATEGORY_DENORMALIZER_ALREADY_CALLED';

    public function __construct(
        private CategoryRepository $categoryRepository
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // Prevent infinite recursion
        $context[self::ALREADY_CALLED] = true;

        // Remove category data temporarily to avoid conflicts with normal denormalization
        $categoryData = $data['category'] ?? null;
        unset($data['category']);

        // First, denormalize the article normally
        $article = $this->denormalizer->denormalize($data, $type, $format, $context);

        // Handle category field specially
        if ($categoryData && is_array($categoryData)) {
            $categories = new ArrayCollection();

            foreach ($categoryData as $singleCategoryData) {
                if (is_string($singleCategoryData)) {
                    // It's an IRI string - extract slug and find existing category
                    $slug = $this->extractSlugFromIri($singleCategoryData);
                    if ($slug) {
                        $existingCategory = $this->categoryRepository->findOneBy(['slug' => $slug]);
                        if ($existingCategory) {
                            $categories->add($existingCategory);
                        }
                    }
                } elseif (is_array($singleCategoryData)) {
                    // It's a new category object - create it
                    $newCategory = new Category();
                    $newCategory->setName($singleCategoryData['name']);
                    $newCategory->setLevel(0); // Root level by default
                    
                    // The EntityLifecycleSubscriber will handle author and slug
                    $categories->add($newCategory);
                }
            }

            // Set the categories on the article
            foreach ($categories as $category) {
                $article->addCategory($category);
            }
        }

        return $article;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Only handle Article denormalization and prevent infinite recursion
        return $type === Article::class 
            && !isset($context[self::ALREADY_CALLED])
            && isset($data['category']) 
            && is_array($data['category']);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Article::class => true,
        ];
    }

    private function extractSlugFromIri(string $iri): ?string
    {
        // Extract slug from IRI like "/api/categories/my-category-slug"
        if (preg_match('#/categories/([^/]+)$#', $iri, $matches)) {
            return $matches[1];
        }
        return null;
    }
}