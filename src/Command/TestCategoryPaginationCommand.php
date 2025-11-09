<?php

namespace App\Command;

use App\Entity\Category;
use ApiPlatform\Doctrine\Orm\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:category-pagination',
    description: 'Test Category pagination to reproduce the UUID error'
)]
class TestCategoryPaginationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Category Pagination (reproducing API Platform behavior)');
        
        try {
            // Create a query similar to what API Platform would create
            $io->section('Test 1: Basic pagination query');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c')
               ->from(Category::class, 'c')
               ->setFirstResult(0)
               ->setMaxResults(30);
            
            // Test the actual query
            $result = $qb->getQuery()->getResult();
            $io->success(sprintf('Basic pagination query successful: %d results', count($result)));
            
            // Test with Doctrine Paginator (what API Platform uses)
            $io->section('Test 2: Doctrine Paginator');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c')
               ->from(Category::class, 'c');
            
            $doctrinePaginator = new DoctrinePaginator($qb->getQuery());
            $doctrinePaginator->setUseOutputWalkers(false);
            
            // This is where the error might occur - when counting total results
            $io->text('Getting count...');
            $count = $doctrinePaginator->count();
            $io->success(sprintf('Total count: %d', $count));
            
            $io->text('Getting iterator...');
            $iterator = $doctrinePaginator->getIterator();
            $items = iterator_to_array($iterator);
            $io->success(sprintf('Iterator results: %d', count($items)));
            
            // Test 3: Simulate what happens during API Platform serialization
            $io->section('Test 3: Simulating API Platform serialization context');
            
            // Test with output walkers (sometimes causes issues)
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('c')
               ->from(Category::class, 'c');
            
            $doctrinePaginator = new DoctrinePaginator($qb->getQuery());
            $doctrinePaginator->setUseOutputWalkers(true);
            
            $io->text('Testing with output walkers enabled...');
            $count = $doctrinePaginator->count();
            $io->success(sprintf('Count with output walkers: %d', $count));
            
        } catch (\Exception $e) {
            $io->error('Error occurred: ' . $e->getMessage());
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());
            
            // Let's check if it's specifically the count query that's failing
            $io->section('Debugging count query');
            try {
                $qb = $this->entityManager->createQueryBuilder();
                $qb->select('COUNT(c.id)')
                   ->from(Category::class, 'c');
                
                $count = $qb->getQuery()->getSingleScalarResult();
                $io->text(sprintf('Manual COUNT query result: %d', $count));
            } catch (\Exception $countException) {
                $io->error('Count query also failed: ' . $countException->getMessage());
            }
            
            return Command::FAILURE;
        }
        
        $io->success('All pagination tests passed!');
        return Command::SUCCESS;
    }
}