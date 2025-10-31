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
    name: 'app:fix-deep-learning-level',
    description: 'Fix the Deep Learning category level based on its parent',
)]
class FixDeepLearningLevelCommand extends Command
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

        $io->title('Fixing Deep Learning Category Level');

        $categoryRepository = $this->entityManager->getRepository(Category::class);

        // Find the categories
        $machineLearning = $categoryRepository->findOneBy(['slug' => 'machine-learning']);
        $deepLearning = $categoryRepository->findOneBy(['slug' => 'deep-learning']);

        if (!$machineLearning) {
            $io->error('Machine Learning category not found');
            return Command::FAILURE;
        }

        if (!$deepLearning) {
            $io->error('Deep Learning category not found');
            return Command::FAILURE;
        }

        $io->section('Current State');
        $io->text("Machine Learning: Level {$machineLearning->getLevel()}");
        $io->text("Deep Learning: Level {$deepLearning->getLevel()}, Parent: " . ($deepLearning->getParent()?->getName() ?? 'None'));

        // Simulate the PATCH operation by updating the parent
        $io->section('Updating Deep Learning parent to Machine Learning');
        
        $oldLevel = $deepLearning->getLevel();
        $deepLearning->setParent($machineLearning);
        $deepLearning->setDescription('le deep learning est une branche de ma machine learning');
        
        // This should trigger our EntityLifecycleSubscriber
        $this->entityManager->flush();

        // Refresh to get updated values
        $this->entityManager->refresh($deepLearning);
        
        $io->section('Updated State');
        $io->text("Deep Learning: Level {$deepLearning->getLevel()}, Parent: " . ($deepLearning->getParent()?->getName() ?? 'None'));
        
        $expectedLevel = $machineLearning->getLevel() + 1;
        if ($deepLearning->getLevel() === $expectedLevel) {
            $io->success("✓ Level correctly updated from {$oldLevel} to {$deepLearning->getLevel()} (expected: {$expectedLevel})");
        } else {
            $io->error("✗ Level not updated correctly. Expected: {$expectedLevel}, Actual: {$deepLearning->getLevel()}");
        }

        // Show the hierarchy
        $io->section('Current Category Hierarchy');
        $this->showCategoryHierarchy($io, $categoryRepository);

        return Command::SUCCESS;
    }

    private function showCategoryHierarchy(SymfonyStyle $io, $categoryRepository): void
    {
        $categories = $categoryRepository->findBy([], ['level' => 'ASC', 'name' => 'ASC']);
        
        foreach ($categories as $category) {
            $indent = str_repeat('  ', $category->getLevel());
            $parentInfo = $category->getParent() ? " (parent: {$category->getParent()->getName()})" : '';
            $io->text("{$indent}- {$category->getName()} [Level: {$category->getLevel()}]{$parentInfo}");
        }
    }
}