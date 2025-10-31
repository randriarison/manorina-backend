<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[AsCommand(
    name: 'test:full-category-hierarchy',
    description: 'Complete test of automatic parent category inclusion with Article creation and persistence'
)]
class TestFullCategoryHierarchyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository,
        private UserRepository $userRepository,
        private DenormalizerInterface $denormalizer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Complete Test of Automatic Parent Category Inclusion');

        // Find the deepest category
        $deepCategory = $this->categoryRepository->findOneBy(['slug' => 'human-rights']);
        
        if (!$deepCategory) {
            $io->error('Could not find "human-rights" category. Please run test:parent-category-inclusion first.');
            return Command::FAILURE;
        }

        // Display the hierarchy
        $io->section('Testing with Category Hierarchy');
        $this->displayCategoryHierarchy($io, $deepCategory);

        // Create an article via denormalization (simulating API call)
        $io->section('Creating Article via Denormalization');
        
        $articleData = [
            'title' => 'Test Article - Full Hierarchy Test',
            'content' => 'This article tests the automatic parent category inclusion functionality.',
            'category' => ['/api/categories/human-rights']
        ];

        $article = new Article();
        
        // Set required fields
        $user = $this->userRepository->findOneBy([]);
        if ($user) {
            $article->setAuthor($user);
        }
        $article->setStatus(ArticleStatus::DRAFT);
        $article->setArticleType(ArticleType::SIMPLE);

        $io->text('Denormalizing article data...');
        $result = $this->denormalizer->denormalize($articleData, Article::class, null, [
            'object_to_populate' => $article
        ]);

        // Persist the article
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        $io->success(sprintf('Article created with ID: %s', $result->getId()));

        // Verify the categories were properly persisted
        $io->section('Verifying Persisted Categories');
        $persistedCategories = $result->getCategory();
        
        $io->text(sprintf('Total categories persisted: %d', $persistedCategories->count()));
        
        foreach ($persistedCategories as $category) {
            $hierarchy = $this->buildHierarchyPath($category);
            $io->text(sprintf('✓ %s (Level %d) - %s', 
                $category->getName(), 
                $category->getLevel(),
                $hierarchy
            ));
        }

        // Verify all expected categories are present
        $expectedCategories = $this->getAllParentCategories($deepCategory);
        $actualSlugs = array_map(fn($cat) => $cat->getSlug(), $persistedCategories->toArray());
        $expectedSlugs = array_map(fn($cat) => $cat->getSlug(), $expectedCategories);

        $missing = array_diff($expectedSlugs, $actualSlugs);
        
        $io->section('Final Verification');
        if (empty($missing)) {
            $io->success('🎉 All parent categories are correctly included and persisted!');
            
            // Show the complete hierarchy verification
            $io->table(['Level', 'Category', 'Slug', 'Status'], array_map(function($cat) use ($actualSlugs) {
                return [
                    $cat->getLevel(),
                    $cat->getName(),
                    $cat->getSlug(),
                    in_array($cat->getSlug(), $actualSlugs) ? '✅ Included' : '❌ Missing'
                ];
            }, $expectedCategories));
            
        } else {
            $io->error('Missing categories: ' . implode(', ', $missing));
            return Command::FAILURE;
        }

        // Cleanup - remove the test article
        $io->section('Cleanup');
        $this->entityManager->remove($result);
        $this->entityManager->flush();
        $io->text('Test article removed from database');

        return Command::SUCCESS;
    }

    private function displayCategoryHierarchy(SymfonyStyle $io, Category $category): void
    {
        $hierarchy = $this->getAllParentCategories($category);
        
        foreach ($hierarchy as $cat) {
            $indent = str_repeat('  ', $cat->getLevel());
            $io->text(sprintf('%s- %s (Level %d, Slug: %s)', 
                $indent, 
                $cat->getName(), 
                $cat->getLevel(),
                $cat->getSlug()
            ));
        }
    }

    private function buildHierarchyPath(Category $category): string
    {
        $path = [];
        $current = $category;
        
        while ($current !== null) {
            array_unshift($path, $current->getName());
            $current = $current->getParent();
        }
        
        return implode(' > ', $path);
    }

    private function getAllParentCategories(Category $category): array
    {
        $categories = [];
        $current = $category;
        
        while ($current !== null) {
            $categories[] = $current;
            $current = $current->getParent();
        }
        
        return array_reverse($categories); // Return from root to leaf
    }
}