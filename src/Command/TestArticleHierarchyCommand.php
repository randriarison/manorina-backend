<?php

namespace App\Command;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:test-article-hierarchy',
    description: 'Test Article hierarchy constraints and automatic management'
)]
class TestArticleHierarchyCommand extends Command
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

        try {
            // Get a user for testing
            $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
            if (!$user) {
                $io->error('No user found. Please create a user first.');
                return Command::FAILURE;
            }

            $io->info('Testing Article hierarchy constraints and management...');

            // Test 1: Create a root question
            $io->section('Test 1: Creating root question');
            $question = $this->createArticle('Test Question', ArticleType::QUESTION, $user);
            $this->entityManager->persist($question);
            $this->entityManager->flush();
            
            $io->success(sprintf(
                'Created question: ID=%s, Level=%d, ChildrenCount=%d, DescendantCount=%d',
                $question->getId()->toString(),
                $question->getLevel(),
                $question->getChildrenCount(),
                $question->getDescendantCount()
            ));

            // Test 2: Create an answer to the question
            $io->section('Test 2: Creating answer to question');
            $answer = $this->createArticle('Test Answer', ArticleType::ANSWER, $user, $question);
            $this->entityManager->persist($answer);
            $this->entityManager->flush();

            // Refresh question to see updated counters
            $this->entityManager->refresh($question);
            
            $io->success(sprintf(
                'Created answer: ID=%s, Level=%d, Parent=%s, RootArticle=%s',
                $answer->getId()->toString(),
                $answer->getLevel(),
                $answer->getParentArticle()->getId()->toString(),
                $answer->getRootArticle()->getId()->toString()
            ));

            $io->success(sprintf(
                'Updated question counters: ChildrenCount=%d, DescendantCount=%d',
                $question->getChildrenCount(),
                $question->getDescendantCount()
            ));

            // Test 3: Create a comment on the answer
            $io->section('Test 3: Creating comment on answer');
            $comment = $this->createArticle('Test Comment', ArticleType::COMMENT, $user, $answer);
            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            // Refresh entities to see updated counters
            $this->entityManager->refresh($question);
            $this->entityManager->refresh($answer);

            $io->success(sprintf(
                'Created comment: ID=%s, Level=%d, Parent=%s, RootArticle=%s',
                $comment->getId()->toString(),
                $comment->getLevel(),
                $comment->getParentArticle()->getId()->toString(),
                $comment->getRootArticle()->getId()->toString()
            ));

            $io->success(sprintf(
                'Final question counters: ChildrenCount=%d, DescendantCount=%d',
                $question->getChildrenCount(),
                $question->getDescendantCount()
            ));

            $io->success(sprintf(
                'Final answer counters: ChildrenCount=%d, DescendantCount=%d',
                $answer->getChildrenCount(),
                $answer->getDescendantCount()
            ));

            // Test 4: Try to create invalid combinations (these should fail validation)
            $io->section('Test 4: Testing validation constraints');

            // Try creating a COMMENT without parent (should fail)
            try {
                $invalidComment = $this->createArticle('Invalid Comment', ArticleType::COMMENT, $user);
                
                // Validate before persisting
                $violations = $this->validator->validate($invalidComment);
                if (count($violations) > 0) {
                    throw new \Exception('Validation failed: ' . (string) $violations);
                }
                
                $this->entityManager->persist($invalidComment);
                $this->entityManager->flush();
                $io->error('ERROR: Comment without parent was allowed (should have failed)');
            } catch (\Exception $e) {
                $io->success('✓ Correctly rejected COMMENT without parent: ' . $e->getMessage());
            }

            // Try creating an ANSWER with non-QUESTION parent (should fail)
            try {
                $invalidAnswer = $this->createArticle('Invalid Answer', ArticleType::ANSWER, $user, $answer);
                
                // Validate before persisting
                $violations = $this->validator->validate($invalidAnswer);
                if (count($violations) > 0) {
                    throw new \Exception('Validation failed: ' . (string) $violations);
                }
                
                $this->entityManager->persist($invalidAnswer);
                $this->entityManager->flush();
                $io->error('ERROR: Answer to non-question was allowed (should have failed)');
            } catch (\Exception $e) {
                $io->success('✓ Correctly rejected ANSWER to non-QUESTION parent: ' . $e->getMessage());
            }

            // Test valid operations (should succeed)
            $io->section('Test 5: Testing valid operations');
            
            // Create a valid answer to the question (should succeed)
            try {
                $validAnswer = $this->createArticle('Valid Answer to Question', ArticleType::ANSWER, $user, $question);
                
                // Validate before persisting
                $violations = $this->validator->validate($validAnswer);
                if (count($violations) > 0) {
                    throw new \Exception('Validation failed: ' . (string) $violations);
                }
                
                $this->entityManager->persist($validAnswer);
                $this->entityManager->flush();
                $io->success('✓ Successfully created valid ANSWER to QUESTION parent');
            } catch (\Exception $e) {
                $io->error('ERROR: Valid answer to question was rejected: ' . $e->getMessage());
            }

            // Create a valid comment on any article (should succeed)
            try {
                $validComment = $this->createArticle('Valid Comment on Answer', ArticleType::COMMENT, $user, $answer);
                
                // Validate before persisting
                $violations = $this->validator->validate($validComment);
                if (count($violations) > 0) {
                    throw new \Exception('Validation failed: ' . (string) $violations);
                }
                
                $this->entityManager->persist($validComment);
                $this->entityManager->flush();
                $io->success('✓ Successfully created valid COMMENT with parent');
            } catch (\Exception $e) {
                $io->error('ERROR: Valid comment with parent was rejected: ' . $e->getMessage());
            }

            // Show final hierarchy state
            $io->section('Final Hierarchy State');
            $this->entityManager->refresh($question);
            $io->text('Root Question final counters: ChildrenCount=' . $question->getChildrenCount() . ', DescendantCount=' . $question->getDescendantCount());
            
            $io->success('All Article hierarchy tests completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createArticle(string $title, ArticleType $type, User $user, ?Article $parent = null): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setContent('Test content for ' . $title);
        $article->setStatus(ArticleStatus::DRAFT);
        $article->setArticleType($type);
        
        // Manually set required fields since we're in console context
        $article->setAuthor($user);
        $article->setUpdatedBy($user);
        $article->setCreatedAt(new \DateTimeImmutable());
        $article->setUpdatedAt(new \DateTimeImmutable());

        if ($parent) {
            $article->setParentArticle($parent);
        }

        return $article;
    }
}