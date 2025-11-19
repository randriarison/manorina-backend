<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\ArticleType;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:article-slug-generation',
    description: 'Test the new Article slug generation logic (title > titleMg > ID)'
)]
class TestArticleSlugGenerationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Article Slug Generation Logic');
        
        // Get a test user
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        if (!$user) {
            $io->error('No user found. Please create a user first.');
            return Command::FAILURE;
        }
        
        $testCases = [
            [
                'description' => 'Article with title only',
                'title' => 'Test Article Title',
                'titleMg' => null,
                'expectedSlugSource' => 'title'
            ],
            [
                'description' => 'Article with titleMg only (empty title)',
                'title' => '', // Empty string instead of null to satisfy NOT NULL constraint
                'titleMg' => 'Lohateny Malagasy',
                'expectedSlugSource' => 'titleMg'
            ],
            [
                'description' => 'Article with both title and titleMg (title should win)',
                'title' => 'English Title',
                'titleMg' => 'Lohateny Malagasy',
                'expectedSlugSource' => 'title'
            ],
            [
                'description' => 'Article with empty title, valid titleMg',
                'title' => '   ',
                'titleMg' => 'Lohateny Malagasy Valid',
                'expectedSlugSource' => 'titleMg'
            ],
            [
                'description' => 'Article with no valid titles (should use ID)',
                'title' => '', // Empty string instead of null
                'titleMg' => null,
                'expectedSlugSource' => 'id'
            ],
            [
                'description' => 'Article with empty titles (should use ID)',
                'title' => '   ',
                'titleMg' => '',
                'expectedSlugSource' => 'id'
            ]
        ];
        
        $passedTests = 0;
        $totalTests = count($testCases);
        $createdArticles = [];
        
        foreach ($testCases as $index => $testCase) {
            $io->section("Test Case " . ($index + 1) . ": " . $testCase['description']);
            
            try {
                $article = new Article();
                $article->setAuthor($user);
                $article->setArticleType(ArticleType::COMMENT); // Use comment to avoid title validation
                $article->setStatus(ArticleStatus::PUBLISHED);
                $article->setContent('Test content for slug generation');
                
                // Always set title (required field - cannot be null)
                $article->setTitle($testCase['title'] ?? '');
                
                if ($testCase['titleMg'] !== null) {
                    $article->setTitleMg($testCase['titleMg']);
                }
                
                $this->entityManager->persist($article);
                $this->entityManager->flush();
                $this->entityManager->refresh($article); // Refresh to get the generated slug
                
                $createdArticles[] = $article;
                
                $slug = $article->getSlug();
                $articleId = (string) $article->getId();
                $title = $article->getTitle();
                $titleMg = $article->getTitleMg();
                
                $io->text("Generated slug: '{$slug}'");
                $io->text("Article ID: {$articleId}");
                $io->text("Title: '{$title}'");
                $io->text("TitleMg: '{$titleMg}'");
                
                // Check which source was used
                $actualSource = 'unknown';
                
                if ($testCase['expectedSlugSource'] === 'title' && $testCase['title']) {
                    $expectedSlugBase = strtolower(str_replace(' ', '-', trim($testCase['title'])));
                    if (str_contains($slug, $expectedSlugBase)) {
                        $actualSource = 'title';
                    }
                } elseif ($testCase['expectedSlugSource'] === 'titleMg' && $testCase['titleMg']) {
                    $expectedSlugBase = strtolower(str_replace(' ', '-', trim($testCase['titleMg'])));
                    if (str_contains($slug, $expectedSlugBase)) {
                        $actualSource = 'titleMg';
                    }
                } elseif ($testCase['expectedSlugSource'] === 'id') {
                    if (str_contains($slug, $articleId)) {
                        $actualSource = 'id';
                    }
                }
                
                $io->text("Expected source: {$testCase['expectedSlugSource']}");
                $io->text("Actual source: {$actualSource}");
                
                if ($actualSource === $testCase['expectedSlugSource']) {
                    $io->success("✅ Test PASSED");
                    $passedTests++;
                } else {
                    $io->error("❌ Test FAILED - Slug source mismatch");
                }
                
            } catch (\Exception $e) {
                $io->error("❌ Test FAILED - Exception: " . $e->getMessage());
            }
            
            $io->newLine();
        }
        
        // Cleanup created articles
        $io->section('Cleaning up test articles');
        foreach ($createdArticles as $article) {
            $this->entityManager->remove($article);
        }
        $this->entityManager->flush();
        $io->text('Test articles cleaned up.');
        
        $io->section('Test Results');
        $io->text("Passed: {$passedTests}/{$totalTests}");
        
        if ($passedTests === $totalTests) {
            $io->success('All tests passed! Article slug generation is working correctly.');
            return Command::SUCCESS;
        } else {
            $io->error('Some tests failed. Please check the slug generation logic.');
            return Command::FAILURE;
        }
    }
}