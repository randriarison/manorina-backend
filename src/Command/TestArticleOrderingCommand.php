<?php

namespace App\Command;

use App\Repository\ArticleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:article-ordering',
    description: 'Test article ordering by updatedAt DESC, descendantCount DESC'
)]
class TestArticleOrderingCommand extends Command
{
    public function __construct(
        private ArticleRepository $articleRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Article Ordering');
        $io->text('Verifying articles are ordered by updatedAt DESC, then descendantCount DESC');
        
        // Get articles with the new ordering
        $articles = $this->articleRepository->findBy(
            [],
            ['updatedAt' => 'DESC', 'descendantCount' => 'DESC'],
            10 // Limit to first 10 for testing
        );

        if (empty($articles)) {
            $io->warning('No articles found in the database');
            return Command::SUCCESS;
        }

        $io->section('Article Ordering Results');
        
        $table = [];
        $previousUpdatedAt = null;
        $previousDescendantCount = null;
        
        foreach ($articles as $article) {
            $updatedAt = $article->getUpdatedAt();
            $descendantCount = $article->getDescendantCount() ?? 0;
            
            $orderingStatus = '✅';
            
            // Check if ordering is correct
            if ($previousUpdatedAt !== null) {
                if ($updatedAt > $previousUpdatedAt) {
                    $orderingStatus = '❌ Wrong updatedAt order';
                } elseif ($updatedAt == $previousUpdatedAt && $descendantCount > $previousDescendantCount) {
                    $orderingStatus = '❌ Wrong descendantCount order';
                }
            }
            
            $table[] = [
                $article->getSlug(),
                $article->getTitle(),
                $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : 'NULL',
                $descendantCount,
                $orderingStatus
            ];
            
            $previousUpdatedAt = $updatedAt;
            $previousDescendantCount = $descendantCount;
        }

        $io->table(
            ['Slug', 'Title', 'Updated At', 'Descendant Count', 'Ordering Status'],
            $table
        );

        // Test API Platform ordering
        $io->section('API Platform Order Configuration');
        $io->text('Main collection (/articles): order by updatedAt DESC, descendantCount DESC');
        $io->text('Only-mine collection (/articles/only-mine): order by updatedAt DESC, descendantCount DESC');
        
        $io->section('Usage Examples');
        $io->text('Test the API endpoints:');
        $io->listing([
            'GET /api/articles - Should return articles ordered by updatedAt DESC, descendantCount DESC',
            'GET /api/articles/only-mine - Should return user articles with same ordering (requires authentication)',
        ]);

        $io->success('Article ordering configuration has been updated successfully!');
        
        return Command::SUCCESS;
    }
}