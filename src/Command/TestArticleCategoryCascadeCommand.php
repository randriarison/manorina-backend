<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\User;
use App\Enum\ArticleType;
use App\Serializer\CategoryDenormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(
    name: 'app:test-article-category-cascade',
    description: 'Test Article creation with cascade persist for categories',
)]
class TestArticleCategoryCascadeCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private CategoryDenormalizer $categoryDenormalizer,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get a test user
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy([]);
        
        if (!$user) {
            $io->error('No user found. Please create a user first.');
            return Command::FAILURE;
        }

        $io->title('Testing Article Creation with Category Cascade Persist');

        // Test 1: Create article with existing category by IRI and new category
        $io->section('Test 1: Mixed existing and new categories');

        try {
            // Simulate API payload
            $articleData = [
                'title' => 'Test Article with Mixed Categories',
                'content' => 'This article tests both existing and new category creation.',
                'articleType' => 'simple',
                'status' => 'published',
                'category' => [
                    '/api/categories/technology',  // Existing category by IRI
                    [
                        'name' => 'Artificial Intelligence',  // New category
                    ]
                ]
            ];

            // Use our custom denormalizer
            $article = $this->categoryDenormalizer->denormalize(
                $articleData, 
                Article::class, 
                null, 
                ['groups' => ['article:write']]
            );

            $article->setAuthor($user);
            
            // Set author for any new categories
            foreach ($article->getCategory() as $category) {
                try {
                    $category->getAuthor(); // Check if author is already set
                } catch (\Error $e) {
                    $category->setAuthor($user); // Set author if not initialized
                }
            }

            $this->entityManager->persist($article);
            $this->entityManager->flush();

            $io->success('✓ Successfully created article with mixed categories');
            $io->text('Categories: ' . implode(', ', $article->getCategory()->map(fn($cat) => $cat->getName())->toArray()));

        } catch (\Exception $e) {
            $io->error('Failed to create article: ' . $e->getMessage());
        }

        // Test 2: Create article with only new categories
        $io->section('Test 2: Only new categories');

        try {
            $articleData2 = [
                'title' => 'Another Test Article',
                'content' => 'This article only creates new categories.',
                'articleType' => 'simple',
                'status' => 'published',
                'category' => [
                    ['name' => 'Machine Learning'],
                    ['name' => 'Deep Learning'],
                ]
            ];

            $article2 = $this->categoryDenormalizer->denormalize(
                $articleData2,
                Article::class,
                null,
                ['groups' => ['article:write']]
            );

            $article2->setAuthor($user);
            
            // Set author for any new categories
            foreach ($article2->getCategory() as $category) {
                try {
                    $category->getAuthor(); // Check if author is already set
                } catch (\Error $e) {
                    $category->setAuthor($user); // Set author if not initialized
                }
            }

            $this->entityManager->persist($article2);
            $this->entityManager->flush();

            $io->success('✓ Successfully created article with only new categories');
            $io->text('Categories: ' . implode(', ', $article2->getCategory()->map(fn($cat) => $cat->getName())->toArray()));

        } catch (\Exception $e) {
            $io->error('Failed to create second article: ' . $e->getMessage());
        }

        // Test 3: Show all categories
        $io->section('All Categories in Database');
        $categoryRepository = $this->entityManager->getRepository(Category::class);
        $allCategories = $categoryRepository->findAll();
        
        foreach ($allCategories as $category) {
            $io->text("- {$category->getName()} (slug: {$category->getSlug()})");
        }

        $io->success('Category cascade persist tests completed!');

        return Command::SUCCESS;
    }
}