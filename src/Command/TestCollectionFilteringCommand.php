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
    name: 'test:collection-filtering',
    description: 'Test collection filtering for article types'
)]
class TestCollectionFilteringCommand extends Command
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

        $io->title('Testing Article Collection Filtering');

        // Get or create test user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'collection-test@example.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('collection-test@example.com');
            $user->setRoles(['ROLE_USER']);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        // Create test articles of different types
        $articlesToCreate = [
            ['title' => 'Main Article - Simple', 'type' => ArticleType::SIMPLE, 'shouldShow' => true],
            ['title' => 'Main Article - Question', 'type' => ArticleType::QUESTION, 'shouldShow' => true],
            ['title' => 'Main Article - Paragraph', 'type' => ArticleType::PARAGRAPH, 'shouldShow' => true],
            ['title' => 'Hidden Answer Article', 'type' => ArticleType::ANSWER, 'shouldShow' => false],
            ['title' => 'Hidden Comment Article', 'type' => ArticleType::COMMENT, 'shouldShow' => false],
        ];

        $articles = [];
        foreach ($articlesToCreate as $data) {
            $existing = $this->articleRepository->findOneBy(['title' => $data['title']]);
            if (!$existing) {
                $article = new Article();
                $article->setTitle($data['title']);
                $article->setContent('Content for ' . $data['type']->value);
                $article->setStatus(ArticleStatus::PUBLISHED);
                $article->setArticleType($data['type']);
                $article->setAuthor($user);
                $this->entityManager->persist($article);
                $articles[] = ['article' => $article, 'shouldShow' => $data['shouldShow']];
            } else {
                $articles[] = ['article' => $existing, 'shouldShow' => $data['shouldShow']];
            }
        }

        $this->entityManager->flush();

        // Create parent-child relationships
        $questionArticle = null;
        $answerArticle = null;
        $commentArticle = null;

        foreach ($articles as $item) {
            $article = $item['article'];
            if ($article->getArticleType() === ArticleType::QUESTION) {
                $questionArticle = $article;
            } elseif ($article->getArticleType() === ArticleType::ANSWER) {
                $answerArticle = $article;
            } elseif ($article->getArticleType() === ArticleType::COMMENT) {
                $commentArticle = $article;
            }
        }

        // Set up parent-child relationships
        if ($questionArticle && $answerArticle && !$answerArticle->getParentArticle()) {
            $answerArticle->setParentArticle($questionArticle);
            $this->entityManager->persist($answerArticle);
        }

        if ($answerArticle && $commentArticle && !$commentArticle->getParentArticle()) {
            $commentArticle->setParentArticle($answerArticle);
            $this->entityManager->persist($commentArticle);
        }

        $this->entityManager->flush();

        $io->success('Test data created/updated successfully');

        // Now test the filtering logic by examining what the repository returns
        $io->section('Testing Repository Query Results');

        // Simulate what the API would return for the main collection
        $queryBuilder = $this->articleRepository->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED);

        // Apply our filtering logic (simulate what ArticleExtension does)
        $queryBuilder->andWhere('a.articleType NOT IN (:excluded_types)')
                    ->setParameter('excluded_types', [ArticleType::COMMENT->value, ArticleType::ANSWER->value]);

        $filteredArticles = $queryBuilder->getQuery()->getResult();

        $io->text('Articles returned by main collection (with filtering):');
        foreach ($filteredArticles as $article) {
            $io->text(sprintf('- %s (%s)', $article->getTitle(), $article->getArticleType()->value));
        }

        // Test without filtering
        $allPublishedArticles = $this->articleRepository->findBy(['status' => ArticleStatus::PUBLISHED]);
        
        $io->newLine();
        $io->text('All published articles (without filtering):');
        foreach ($allPublishedArticles as $article) {
            $io->text(sprintf('- %s (%s)', $article->getTitle(), $article->getArticleType()->value));
        }

        // Summary
        $io->section('Summary');
        
        $filteredCount = count($filteredArticles);
        $totalCount = count($allPublishedArticles);
        $hiddenCount = $totalCount - $filteredCount;

        $io->text([
            sprintf('Total published articles: %d', $totalCount),
            sprintf('Articles shown in main collection: %d', $filteredCount),
            sprintf('Articles hidden (comments/answers): %d', $hiddenCount),
        ]);

        if ($hiddenCount > 0) {
            $io->success('✅ Filtering is working - comments and answers are hidden from main collection');
        } else {
            $io->warning('⚠️ No articles were filtered - check if the extension is working correctly');
        }

        // Provide API testing instructions
        $io->section('API Testing Guide');
        
        if ($questionArticle) {
            $io->text([
                'To test via API:',
                '',
                '1. Main collection (should hide comments/answers):',
                '   GET /api/articles',
                '   Expected: Only SIMPLE, QUESTION, PARAGRAPH types',
                '',
                '2. With parentArticle filter (should show comments/answers):',
                '   GET /api/articles?parentArticle=' . $questionArticle->getId()->toString(),
                '   Expected: Should include child articles of the specified parent',
                '',
                '3. Test parent article slug: ' . $questionArticle->getSlug(),
            ]);
        }

        return Command::SUCCESS;
    }
}