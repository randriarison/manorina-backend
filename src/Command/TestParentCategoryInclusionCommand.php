<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[AsCommand(
    name: 'test:parent-category-inclusion',
    description: 'Test automatic parent category inclusion when adding categories to articles'
)]
class TestParentCategoryInclusionCommand extends Command
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

        $io->title('Testing Automatic Parent Category Inclusion');

        // First let's create a deeper hierarchy for testing
        $this->createDeepHierarchy($io);
        
        // Find the deepest category to test with
        $deepCategory = $this->categoryRepository->findOneBy(['slug' => 'human-rights']);
        
        if (!$deepCategory) {
            $io->error('Could not find "human-rights" category. Failed to create hierarchy.');
            return Command::FAILURE;
        }

        // Display the category hierarchy for this category
        $io->section(sprintf('Category Hierarchy for "%s"', $deepCategory->getSlug()));
        $this->displayCategoryHierarchy($io, $deepCategory);

        // Create a test article
        $article = new Article();
        $article->setTitle('Test Article for Parent Category Inclusion');
        $article->setContent('This is a test article to verify parent category inclusion.');
        
        // Simulate the denormalization process with the deepest category
        $testData = [
            'category' => ['/api/categories/' . $deepCategory->getSlug()]
        ];

        $io->section('Simulating Article Update with Deep Category');
        $io->text('Adding category: /api/categories/' . $deepCategory->getSlug());
        $io->text('Expected: All parent categories should be automatically included');

        // Use the denormalizer to process the article with categories
        $result = $this->denormalizer->denormalize($testData, Article::class, null, [
            'object_to_populate' => $article
        ]);

        // Display the resulting categories
        $io->section('Resulting Categories in Article');
        $categories = $result->getCategory();
        
        if ($categories->isEmpty()) {
            $io->warning('No categories were added to the article.');
        } else {
            $io->text(sprintf('Total categories added: %d', $categories->count()));
            
            foreach ($categories as $category) {
                $hierarchy = $this->buildHierarchyPath($category);
                $io->text(sprintf('- %s (Level %d) - Path: %s', 
                    $category->getName(), 
                    $category->getLevel(),
                    $hierarchy
                ));
            }
        }

        // Verify that all parent categories are included
        $io->section('Verification');
        $expectedCategories = $this->getAllParentCategories($deepCategory);
        $actualSlugs = array_map(fn($cat) => $cat->getSlug(), $categories->toArray());
        $expectedSlugs = array_map(fn($cat) => $cat->getSlug(), $expectedCategories);

        $missing = array_diff($expectedSlugs, $actualSlugs);
        $unexpected = array_diff($actualSlugs, $expectedSlugs);

        if (empty($missing) && empty($unexpected)) {
            $io->success('✅ All parent categories are correctly included!');
        } else {
            if (!empty($missing)) {
                $io->error('Missing expected categories: ' . implode(', ', $missing));
            }
            if (!empty($unexpected)) {
                $io->warning('Unexpected categories found: ' . implode(', ', $unexpected));
            }
        }

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

    private function createDeepHierarchy(SymfonyStyle $io): void
    {
        $io->section('Creating Test Hierarchy');
        
        // Get a user for the author field
        $user = $this->userRepository->findOneBy([]);
        if (!$user) {
            $user = new User();
            $user->setEmail('test@example.com');
            $user->setPassword('test');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
        
        // Find constitutions category (Level 1)
        $constitutions = $this->categoryRepository->findOneBy(['slug' => 'constitutions']);
        
        // Check if constitutional-law already exists
        $constitutionalLaw = $this->categoryRepository->findOneBy(['slug' => 'constitutional-law']);
        
        if (!$constitutionalLaw && $constitutions) {
            $constitutionalLaw = new Category();
            $constitutionalLaw->setName('Constitutional Law');
            $constitutionalLaw->setSlug('constitutional-law');
            $constitutionalLaw->setParent($constitutions);
            $constitutionalLaw->setAuthor($user);
            
            $this->entityManager->persist($constitutionalLaw);
            $this->entityManager->flush();
            
            $io->text('Created "Constitutional Law" category as child of "Constitutions"');
        }
        
        // Create an even deeper category
        $humanRights = $this->categoryRepository->findOneBy(['slug' => 'human-rights']);
        
        if (!$humanRights && $constitutionalLaw) {
            $humanRights = new Category();
            $humanRights->setName('Human Rights');
            $humanRights->setSlug('human-rights');
            $humanRights->setParent($constitutionalLaw);
            $humanRights->setAuthor($user);
            
            $this->entityManager->persist($humanRights);
            $this->entityManager->flush();
            
            $io->text('Created "Human Rights" category as child of "Constitutional Law"');
        }
    }
}