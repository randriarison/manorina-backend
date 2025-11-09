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
    name: 'test:category-getcollection',
    description: 'Test Category GetCollection to reproduce the UUID error'
)]
class TestCategoryGetCollectionCommand extends Command
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
        
        $io->title('Testing Category GetCollection');
        
        try {
            // Test 1: Simple repository findAll
            $io->section('Test 1: Repository findAll()');
            $categories = $this->categoryRepository->findAll();
            $io->success(sprintf('Found %d categories using repository findAll()', count($categories)));
            
            foreach ($categories as $category) {
                $io->text(sprintf(
                    'Category: %s (Level: %d, ID type: %s)',
                    $category->getName(),
                    $category->getLevel(),
                    get_class($category->getId())
                ));
            }
            
            // Test 2: Query Builder
            $io->section('Test 2: Query Builder');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c')
               ->from(Category::class, 'c');
            
            $result = $qb->getQuery()->getResult();
            $io->success(sprintf('Found %d categories using QueryBuilder', count($result)));
            
            // Test 3: Reproduce API Platform-like query
            $io->section('Test 3: API Platform-like query with ordering');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c')
               ->from(Category::class, 'c')
               ->orderBy('c.level', 'ASC')
               ->addOrderBy('c.name', 'ASC');
            
            $result = $qb->getQuery()->getResult();
            $io->success(sprintf('Found %d categories using ordered QueryBuilder', count($result)));
            
            // Test 4: Check for potential joins that might cause issues
            $io->section('Test 4: Query with joins');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c', 'p', 'children')
               ->from(Category::class, 'c')
               ->leftJoin('c.parent', 'p')
               ->leftJoin('c.children', 'children');
            
            $result = $qb->getQuery()->getResult();
            $io->success(sprintf('Found %d categories with joins', count($result)));
            
        } catch (\Exception $e) {
            $io->error('Error occurred: ' . $e->getMessage());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
        
        $io->success('All category queries executed successfully!');
        return Command::SUCCESS;
    }
}