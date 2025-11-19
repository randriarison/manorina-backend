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
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'test:article-content-validation',
    description: 'Test the custom Article content validation rules'
)]
class TestArticleContentValidationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing Article Content Validation');
        
        // Get a test user
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        if (!$user) {
            $io->error('No user found. Please create a user first.');
            return Command::FAILURE;
        }
        
        $testCases = [
            // Test Case 1: Valid simple article
            [
                'description' => 'Valid SIMPLE article with both title and content > 4 chars',
                'articleType' => ArticleType::SIMPLE,
                'title' => 'Valid Title',
                'titleMg' => null,
                'content' => 'Valid content here',
                'contentMg' => null,
                'expectedValid' => true
            ],
            
            // Test Case 2: Invalid simple article - no valid title
            [
                'description' => 'Invalid SIMPLE article - title too short',
                'articleType' => ArticleType::SIMPLE,
                'title' => 'Hi',
                'titleMg' => '   ',
                'content' => 'Valid content here',
                'contentMg' => null,
                'expectedValid' => false
            ],
            
            // Test Case 3: Invalid article - no valid content
            [
                'description' => 'Invalid article - content too short',
                'articleType' => ArticleType::SIMPLE,
                'title' => 'Valid Title',
                'titleMg' => null,
                'content' => 'Hi',
                'contentMg' => '    ',
                'expectedValid' => false
            ],
            
            // Test Case 4: Comment content validation (will fail parent validation, but content should be OK)
            [
                'description' => 'COMMENT content validation - title not required, content valid',
                'articleType' => ArticleType::COMMENT,
                'title' => null,
                'titleMg' => null,
                'content' => 'Valid comment content here',
                'contentMg' => null,
                'expectedValid' => false, // Will fail due to missing parent, but not content validation
                'checkContentValidation' => true
            ],
            
            // Test Case 5: Answer content validation (will fail parent validation, but content should be OK)
            [
                'description' => 'ANSWER content validation - title not required, content valid',
                'articleType' => ArticleType::ANSWER,
                'title' => null,
                'titleMg' => null,
                'content' => 'Valid answer content here',
                'contentMg' => null,
                'expectedValid' => false, // Will fail due to missing parent, but not content validation
                'checkContentValidation' => true
            ],
            
            // Test Case 6: Valid paragraph (title not required)
            [
                'description' => 'Valid PARAGRAPH - title not required for paragraphs',
                'articleType' => ArticleType::PARAGRAPH,
                'title' => null,
                'titleMg' => null,
                'content' => 'Valid paragraph content here',
                'contentMg' => null,
                'expectedValid' => true
            ],
            
            // Test Case 7: Valid with Malagasy content only
            [
                'description' => 'Valid article with only Malagasy content',
                'articleType' => ArticleType::SIMPLE,
                'title' => null,
                'titleMg' => 'Lohateny marina',
                'content' => null,
                'contentMg' => 'Votoatin-kevitra marina eto',
                'expectedValid' => true
            ]
        ];
        
        $passedTests = 0;
        $totalTests = count($testCases);
        
        foreach ($testCases as $index => $testCase) {
            $io->section("Test Case " . ($index + 1) . ": " . $testCase['description']);
            
            $article = new Article();
            $article->setAuthor($user);
            $article->setArticleType($testCase['articleType']);
            $article->setStatus(ArticleStatus::PUBLISHED);
            
            if ($testCase['title']) {
                $article->setTitle($testCase['title']);
            }
            if ($testCase['titleMg']) {
                $article->setTitleMg($testCase['titleMg']);
            }
            if ($testCase['content']) {
                $article->setContent($testCase['content']);
            }
            if ($testCase['contentMg']) {
                $article->setContentMg($testCase['contentMg']);
            }
            
            $violations = $this->validator->validate($article);
            $isValid = count($violations) === 0;
            
            // For special test cases, check if our specific validation works
            if (isset($testCase['checkContentValidation'])) {
                $contentValidationPassed = true;
                foreach ($violations as $violation) {
                    $message = $violation->getMessage();
                    if (str_contains($message, 'title field') || str_contains($message, 'content field')) {
                        $contentValidationPassed = false;
                        break;
                    }
                }
                
                $io->text("Expected: Content validation should PASS (other validations may fail)");
                $io->text("Actual: Content validation " . ($contentValidationPassed ? 'PASSED' : 'FAILED'));
                
                if ($violations->count() > 0) {
                    $io->text("Validation errors:");
                    foreach ($violations as $violation) {
                        $io->text("  - " . $violation->getPropertyPath() . ": " . $violation->getMessage());
                    }
                }
                
                if ($contentValidationPassed) {
                    $io->success("✅ Content validation PASSED (as expected)");
                    $passedTests++;
                } else {
                    $io->error("❌ Content validation FAILED");
                }
            } else {
                $io->text("Expected: " . ($testCase['expectedValid'] ? 'VALID' : 'INVALID'));
                $io->text("Actual: " . ($isValid ? 'VALID' : 'INVALID'));
                
                if ($violations->count() > 0) {
                    $io->text("Validation errors:");
                    foreach ($violations as $violation) {
                        $io->text("  - " . $violation->getPropertyPath() . ": " . $violation->getMessage());
                    }
                }
                
                if ($isValid === $testCase['expectedValid']) {
                    $io->success("✅ Test PASSED");
                    $passedTests++;
                } else {
                    $io->error("❌ Test FAILED");
                }
            }
            
            $io->newLine();
        }
        
        $io->section('Test Results');
        $io->text("Passed: {$passedTests}/{$totalTests}");
        
        if ($passedTests === $totalTests) {
            $io->success('All tests passed! Article content validation is working correctly.');
            return Command::SUCCESS;
        } else {
            $io->error('Some tests failed. Please check the validation logic.');
            return Command::FAILURE;
        }
    }
}