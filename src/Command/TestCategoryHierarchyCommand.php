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

#[AsCommand(
    name: 'app:test-category-hierarchy',
    description: 'Test Category hierarchy level management',
)]
class TestCategoryHierarchyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Category Hierarchy Level Management');

        // Get a test user
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy([]);
        
        if (!$user) {
            $io->error('No user found. Please create a user first.');
            return Command::FAILURE;
        }

        $categoryRepository = $this->entityManager->getRepository(Category::class);

        // Test 1: Create a root category
        $io->section('Test 1: Creating root category');
        
        $rootCategory = new Category();
        $rootCategory->setName('Test Root Category');
        $rootCategory->setAuthor($user);
        // Level should be auto-set to 0
        
        $this->entityManager->persist($rootCategory);
        $this->entityManager->flush();
        
        $io->success("Root category created: Level = {$rootCategory->getLevel()}");

        // Test 2: Create a child category
        $io->section('Test 2: Creating child category');
        
        $childCategory = new Category();
        $childCategory->setName('Test Child Category');
        $childCategory->setAuthor($user);
        $childCategory->setParent($rootCategory);
        // Level should be auto-set to 1
        
        $this->entityManager->persist($childCategory);
        $this->entityManager->flush();
        
        $io->success("Child category created: Level = {$childCategory->getLevel()}");

        // Test 3: Create a grandchild category
        $io->section('Test 3: Creating grandchild category');
        
        $grandchildCategory = new Category();
        $grandchildCategory->setName('Test Grandchild Category');
        $grandchildCategory->setAuthor($user);
        $grandchildCategory->setParent($childCategory);
        // Level should be auto-set to 2
        
        $this->entityManager->persist($grandchildCategory);
        $this->entityManager->flush();
        
        $io->success("Grandchild category created: Level = {$grandchildCategory->getLevel()}");

        // Test 4: Update parent (move grandchild to be child of root)
        $io->section('Test 4: Moving grandchild to be direct child of root');
        
        $oldParentLevel = $grandchildCategory->getParent()?->getLevel() ?? 'none';
        $grandchildCategory->setParent($rootCategory);
        
        $this->entityManager->flush();
        
        $io->success("Grandchild moved: Old parent level = {$oldParentLevel}, New level = {$grandchildCategory->getLevel()}");

        // Test 5: Create another child and verify existing children are updated
        $io->section('Test 5: Creating another child under the moved category');
        
        $greatGrandchild = new Category();
        $greatGrandchild->setName('Test Great-Grandchild Category');
        $greatGrandchild->setAuthor($user);
        $greatGrandchild->setParent($grandchildCategory);
        
        $this->entityManager->persist($greatGrandchild);
        $this->entityManager->flush();
        
        $io->success("Great-grandchild created: Level = {$greatGrandchild->getLevel()}");

        // Show final hierarchy
        $io->section('Final Category Hierarchy');
        
        $allCategories = $categoryRepository->findBy(['author' => $user], ['level' => 'ASC', 'name' => 'ASC']);
        foreach ($allCategories as $category) {
            $indent = str_repeat('  ', $category->getLevel());
            $parentName = $category->getParent()?->getName() ?? 'None';
            $io->text("{$indent}- {$category->getName()} (Level: {$category->getLevel()}, Parent: {$parentName})");
        }

        // Cleanup
        $io->section('Cleaning up test categories');
        foreach ($allCategories as $category) {
            if (strpos($category->getName(), 'Test ') === 0) {
                $this->entityManager->remove($category);
            }
        }
        $this->entityManager->flush();
        
        $io->success('Test categories cleaned up');
        $io->success('Category hierarchy level management test completed!');

        return Command::SUCCESS;
    }
}