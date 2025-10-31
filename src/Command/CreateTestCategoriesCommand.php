<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:create-test-categories',
    description: 'Create some test categories for testing cascade persist functionality',
)]
class CreateTestCategoriesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create a test user if one doesn't exist
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy([]) ?? $this->createTestUser();

        $io->title('Creating Test Categories');

        // Clear existing test categories
        $categoryRepository = $this->entityManager->getRepository(Category::class);
        $existingCategories = $categoryRepository->findAll();
        foreach ($existingCategories as $category) {
            $this->entityManager->remove($category);
        }
        $this->entityManager->flush();
        
        if (count($existingCategories) > 0) {
            $io->text('Cleared ' . count($existingCategories) . ' existing categories');
        }

        // Create some sample categories
        $categories = [
            'Technology' => 'All about technology and programming',
            'Science' => 'Scientific discussions and discoveries',  
            'Sports' => 'Sports news and discussions',
            'Health' => 'Health and wellness topics',
            'Education' => 'Educational content and resources'
        ];

        foreach ($categories as $name => $description) {
            $category = new Category();
            $category->setName($name);
            $category->setAuthor($user);
            $category->setLevel(0); // Root level categories
            
            // Generate slug manually
            $slug = $this->slugger->slug($name)->lower();
            $category->setSlug((string) $slug);

            $this->entityManager->persist($category);
            $io->text("Created category: $name (slug: $slug)");
        }

        $this->entityManager->flush();

        $io->success('Test categories created successfully!');
        $io->note('You can now test the Article creation with both existing categories (by IRI) and new categories (by object) in the same request.');

        // Show some example usage
        $io->section('Example API Usage');
        $io->text([
            'POST /api/articles',
            '{',
            '  "title": "My Article",',
            '  "content": "Article content here",',
            '  "articleType": "SIMPLE",',
            '  "category": [',
            '    "/api/categories/technology",  // Reference existing category by IRI',
            '    {                             // Create new category inline',
            '      "name": "New Category"',
            '    }',
            '  ]',
            '}'
        ]);

        return Command::SUCCESS;
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test User');
        
        $this->entityManager->persist($user);
        
        return $user;
    }
}