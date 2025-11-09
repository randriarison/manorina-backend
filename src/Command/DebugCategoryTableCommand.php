<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'debug:category-table',
    description: 'Debug the category table structure and UUID issue'
)]
class DebugCategoryTableCommand extends Command
{
    public function __construct(
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Debugging Category Table Structure');
        
        try {
            // Check if category table exists
            $io->section('Checking if category table exists');
            $tables = $this->connection->createSchemaManager()->listTableNames();
            $categoryTableExists = in_array('category', $tables);
            
            if ($categoryTableExists) {
                $io->success('Category table exists');
                
                // Get table structure
                $io->section('Category table structure');
                $table = $this->connection->createSchemaManager()->introspectTable('category');
                
                foreach ($table->getColumns() as $column) {
                    $io->text(sprintf(
                        'Column: %s, Type: %s, Nullable: %s',
                        $column->getName(),
                        $column->getType()->getName(),
                        $column->getNotnull() ? 'NO' : 'YES'
                    ));
                }
                
                // Check if there are any categories
                $io->section('Checking for existing categories');
                $count = $this->connection->fetchOne('SELECT COUNT(*) FROM category');
                $io->text("Number of categories in database: {$count}");
                
                if ($count > 0) {
                    $io->section('Sample category data');
                    $categories = $this->connection->fetchAllAssociative('SELECT * FROM category LIMIT 3');
                    
                    foreach ($categories as $category) {
                        $io->text("ID: {$category['id']}, Name: {$category['name']}, Type of ID: " . gettype($category['id']));
                    }
                }
                
            } else {
                $io->error('Category table does not exist!');
                $io->text('Available tables:');
                foreach ($tables as $table) {
                    $io->text("- {$table}");
                }
            }
            
        } catch (\Exception $e) {
            $io->error('Error accessing database: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}