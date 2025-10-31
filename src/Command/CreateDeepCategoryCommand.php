<?php

namespace App\Command;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create:deep-category',
    description: 'Create a deeper category hierarchy for testing'
)]
class CreateDeepCategoryCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CategoryRepository $categoryRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Creating Deep Category Hierarchy');

        // Find the deep-learning category
        $deepLearning = $this->categoryRepository->findOneBy(['slug' => 'deep-learning']);
        
        if (!$deepLearning) {
            $io->error('Deep Learning category not found');
            return Command::FAILURE;
        }

        // Check if Neural Networks already exists
        $neuralNetworks = $this->categoryRepository->findOneBy(['slug' => 'neural-networks']);
        
        if ($neuralNetworks) {
            $io->warning('Neural Networks category already exists');
            return Command::SUCCESS;
        }

        // Create Neural Networks category
        $neuralNetworks = new Category();
        $neuralNetworks->setName('Neural Networks');
        $neuralNetworks->setSlug('neural-networks');
        $neuralNetworks->setParent($deepLearning);
        
        $this->entityManager->persist($neuralNetworks);
        $this->entityManager->flush();

        $io->success(sprintf('Created Neural Networks category (ID: %d) with parent Deep Learning', $neuralNetworks->getId()));

        // Display the hierarchy
        $io->section('Current Hierarchy');
        $this->displayCategoryHierarchy($io, $neuralNetworks);

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