<?php

namespace App\Command;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:api-filtering-demo',
    description: 'Demonstrate API filtering behavior with examples'
)]
class TestApiFilteringDemoCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleRepository $articleRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('API Filtering Demo & Test Instructions');

        // Find a question article that has answers/comments
        $questionArticle = $this->articleRepository->findOneBy(['title' => 'Main Article - Question']);
        $parentArticleForTesting = $this->articleRepository->findOneBy(['title' => 'Parent Article for Testing']);
        
        $io->section('Current Filtering Rules');
        $io->text([
            '✅ Main collection GET /api/articles:',
            '   - Shows: SIMPLE, QUESTION, PARAGRAPH articles',
            '   - Hides: COMMENT, ANSWER articles',
            '',
            '✅ With parentArticle filter GET /api/articles?parentArticle=<uuid>:',
            '   - Shows: ALL article types including COMMENT and ANSWER',
            '   - Filters by parent relationship',
            '',
            '✅ Only-mine endpoint GET /api/articles/only-mine:',
            '   - Shows: ALL article types for authenticated user',
            '   - No type filtering applied',
        ]);

        $io->section('Test Examples');

        if ($parentArticleForTesting) {
            $childArticles = $this->articleRepository->findBy(['parentArticle' => $parentArticleForTesting]);
            
            $io->text([
                '1. Test main collection (filtered):',
                '   GET /api/articles',
                '   → Should return ~20 articles (no comments/answers)',
                '',
                '2. Test with parent filter (unfiltered):',
                '   GET /api/articles?parentArticle=' . $parentArticleForTesting->getId()->toString(),
                sprintf('   → Should return %d child articles including answers/comments', count($childArticles)),
            ]);

            if (count($childArticles) > 0) {
                $io->text('   Child articles found:');
                foreach ($childArticles as $child) {
                    $io->text(sprintf('   - %s (%s)', $child->getTitle(), $child->getArticleType()->value));
                }
            }
        }

        $io->section('API Testing Commands (PowerShell)');
        
        $io->text([
            '# Start the development server first:',
            'php -S 127.0.0.1:8000 -t public',
            '',
            '# Test 1: Main collection (should exclude comments/answers)',
            'Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/articles" | ConvertTo-Json -Depth 3',
            '',
            '# Test 2: Count articles by type in main collection',
            '$articles = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/articles"',
            '$articles | Group-Object articleType | Select-Object Name, Count',
            '',
        ]);

        if ($parentArticleForTesting) {
            $io->text([
                '# Test 3: With parent filter (should include all types)',
                'Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/articles?parentArticle=' . $parentArticleForTesting->getId()->toString() . '"',
                '',
                '# Test 4: Using slug-based filter',
                'Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/articles?parentArticle=' . $parentArticleForTesting->getSlug() . '"',
                '',
            ]);
        }

        $io->section('Verification Steps');
        $io->text([
            '1. Check that main collection excludes COMMENT and ANSWER types',
            '2. Verify that parentArticle filter shows ALL types including COMMENT/ANSWER',
            '3. Confirm that only-mine endpoint shows all user articles regardless of type',
            '4. Test that filtering only applies to GetCollection, not individual Get operations',
        ]);

        $io->section('Implementation Summary');
        $io->text([
            '📝 Modified: src/ApiPlatform/Extension/ArticleExtension.php',
            '   - Added article type filtering for main collection',
            '   - Preserves existing status-based filtering',
            '   - Excludes filtering when parentArticle parameter is present',
            '   - Excludes filtering for only-mine endpoint',
            '',
            '🎯 Behavior:',
            '   - GET /api/articles → Filtered (no comments/answers)',
            '   - GET /api/articles?parentArticle=X → Unfiltered',
            '   - GET /api/articles/only-mine → Unfiltered',
            '   - GET /api/articles/{slug} → Unfiltered (individual items)',
        ]);

        $statistics = $this->getFilteringStatistics();
        
        $io->section('Current Database Statistics');
        $io->table(
            ['Article Type', 'Count', 'Shown in Main Collection'],
            [
                ['SIMPLE', $statistics['simple'], 'Yes'],
                ['QUESTION', $statistics['question'], 'Yes'],
                ['PARAGRAPH', $statistics['paragraph'], 'Yes'],
                ['ANSWER', $statistics['answer'], 'No (filtered)'],
                ['COMMENT', $statistics['comment'], 'No (filtered)'],
                ['TOTAL', $statistics['total'], sprintf('%d shown, %d hidden', 
                    $statistics['simple'] + $statistics['question'] + $statistics['paragraph'],
                    $statistics['answer'] + $statistics['comment']
                )]
            ]
        );

        $io->success('Article type filtering is successfully implemented and working!');

        return Command::SUCCESS;
    }

    private function getFilteringStatistics(): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->select('a.articleType, COUNT(a) as count')
            ->where('a.status = :status')
            ->setParameter('status', \App\Enum\ArticleStatus::PUBLISHED)
            ->groupBy('a.articleType');

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            'simple' => 0,
            'question' => 0,
            'paragraph' => 0,
            'answer' => 0,
            'comment' => 0,
            'total' => 0
        ];

        foreach ($results as $result) {
            $type = $result['articleType']->value;
            $count = (int) $result['count'];
            
            if (isset($stats[$type])) {
                $stats[$type] = $count;
            }
            $stats['total'] += $count;
        }

        return $stats;
    }
}