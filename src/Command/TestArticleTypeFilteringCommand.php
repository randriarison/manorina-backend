<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:article-type-filtering',
    description: 'Test that comments and answers are filtered from main collection'
)]
class TestArticleTypeFilteringCommand extends Command
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

        $io->title('Testing Article Type Filtering');

        // Get or create test user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'filtering-test@example.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('filtering-test@example.com');
            $user->setRoles(['ROLE_USER']);
            $this->entityManager->persist($user);
        }

        // Create test articles of different types
        $testArticles = [
            [
                'title' => 'Simple Article for Filtering Test',
                'type' => ArticleType::SIMPLE,
                'should_appear' => true
            ],
            [
                'title' => 'Question Article for Filtering Test',
                'type' => ArticleType::QUESTION,
                'should_appear' => true
            ],
            [
                'title' => 'Paragraph Article for Filtering Test',
                'type' => ArticleType::PARAGRAPH,
                'should_appear' => true
            ],
            [
                'title' => 'Answer Article for Filtering Test',
                'type' => ArticleType::ANSWER,
                'should_appear' => false
            ],
            [
                'title' => 'Comment Article for Filtering Test',
                'type' => ArticleType::COMMENT,
                'should_appear' => false
            ]
        ];

        $createdArticles = [];
        
        foreach ($testArticles as $articleData) {
            // Check if article already exists
            $existingArticle = $this->articleRepository->findOneBy(['title' => $articleData['title']]);
            
            if (!$existingArticle) {
                $article = new Article();
                $article->setTitle($articleData['title']);
                $article->setContent('Test content for ' . $articleData['type']->value . ' article');
                $article->setStatus(ArticleStatus::PUBLISHED);
                $article->setArticleType($articleData['type']);
                $article->setAuthor($user);
                
                $this->entityManager->persist($article);
                $createdArticles[] = $article;
            } else {
                $createdArticles[] = $existingArticle;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Created/found %d test articles', count($createdArticles)));

        // Test the filtering by using the repository methods
        $io->section('Testing Repository Filtering');

        // Get all articles without any filters (this should use ArticleExtension)
        $allArticles = $this->articleRepository->findBy(['status' => ArticleStatus::PUBLISHED]);
        
        $io->text('All published articles found in repository:');
        
        $simpleCount = 0;
        $questionCount = 0; 
        $paragraphCount = 0;
        $answerCount = 0;
        $commentCount = 0;

        foreach ($allArticles as $article) {
            $type = $article->getArticleType();
            switch ($type) {
                case ArticleType::SIMPLE:
                    $simpleCount++;
                    break;
                case ArticleType::QUESTION:
                    $questionCount++;
                    break;
                case ArticleType::PARAGRAPH:
                    $paragraphCount++;
                    break;
                case ArticleType::ANSWER:
                    $answerCount++;
                    break;
                case ArticleType::COMMENT:
                    $commentCount++;
                    break;
            }
        }

        $io->table(
            ['Article Type', 'Count', 'Should appear in main listing'],
            [
                ['SIMPLE', $simpleCount, 'Yes'],
                ['QUESTION', $questionCount, 'Yes'],
                ['PARAGRAPH', $paragraphCount, 'Yes'],
                ['ANSWER', $answerCount, 'No (filtered out)'],
                ['COMMENT', $commentCount, 'No (filtered out)']
            ]
        );

        // Instructions for API testing
        $io->section('API Testing Instructions');
        $io->text([
            'To test the filtering via API:',
            '',
            '1. Test main collection (should NOT include comments/answers):',
            '   GET /api/articles',
            '',
            '2. Test with parentArticle filter (should include comments/answers):',
            '   GET /api/articles?parentArticle=/api/articles/some-parent-slug',
            '',
            '3. Create a parent-child relationship first:',
        ]);

        // Find a simple/question article to use as parent
        $parentArticle = null;
        foreach ($createdArticles as $article) {
            if (in_array($article->getArticleType(), [ArticleType::SIMPLE, ArticleType::QUESTION])) {
                $parentArticle = $article;
                break;
            }
        }

        if ($parentArticle) {
            $io->text('   Parent article found: ' . $parentArticle->getTitle());
            $io->text('   Slug: ' . $parentArticle->getSlug());
            
            // Create a child article if not exists
            $childTitle = 'Child Answer for ' . $parentArticle->getTitle();
            $childArticle = $this->articleRepository->findOneBy(['title' => $childTitle]);
            
            if (!$childArticle) {
                $childArticle = new Article();
                $childArticle->setTitle($childTitle);
                $childArticle->setContent('This is a child answer');
                $childArticle->setStatus(ArticleStatus::PUBLISHED);
                $childArticle->setArticleType(ArticleType::ANSWER);
                $childArticle->setAuthor($user);
                $childArticle->setParentArticle($parentArticle);
                
                $this->entityManager->persist($childArticle);
                $this->entityManager->flush();
                
                $io->info('Created child article: ' . $childArticle->getTitle());
            }

            $io->text([
                '',
                '4. Test filtering with the created parent-child relationship:',
                '   GET /api/articles?parentArticle=' . $parentArticle->getId()->toString(),
                '   (This should return the child answer)'
            ]);
        }

        return Command::SUCCESS;
    }
}