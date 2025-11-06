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
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(
    name: 'test:parent-article-edge-cases',
    description: 'Test edge cases for parent article denormalization'
)]
class TestParentArticleEdgeCasesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Parent Article Edge Cases');

        // Get test user
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'parent-article-test@example.com']);
        
        if (!$user) {
            $io->error('Test user not found. Run test:parent-article-denormalization first.');
            return Command::FAILURE;
        }

        $testCases = [
            [
                'name' => 'Invalid parent article slug',
                'data' => [
                    'title' => 'Test Invalid Parent',
                    'content' => 'Content',
                    'status' => 'published',
                    'articleType' => 'answer',
                    'parentArticle' => '/api/articles/non-existent-slug'
                ]
            ],
            [
                'name' => 'Malformed IRI',
                'data' => [
                    'title' => 'Test Malformed IRI',
                    'content' => 'Content',
                    'status' => 'published', 
                    'articleType' => 'answer',
                    'parentArticle' => 'invalid-iri-format'
                ]
            ],
            [
                'name' => 'Empty parent article',
                'data' => [
                    'title' => 'Test Empty Parent',
                    'content' => 'Content',
                    'status' => 'published',
                    'articleType' => 'answer', 
                    'parentArticle' => ''
                ]
            ],
            [
                'name' => 'No parent article field',
                'data' => [
                    'title' => 'Test No Parent Field',
                    'content' => 'Content',
                    'status' => 'published',
                    'articleType' => 'simple'
                ]
            ]
        ];

        foreach ($testCases as $testCase) {
            $io->section("Testing: " . $testCase['name']);
            
            try {
                $article = $this->serializer->deserialize(
                    json_encode($testCase['data']),
                    Article::class,
                    'json',
                    ['groups' => ['article:write']]
                );

                $article->setAuthor($user);
                $this->entityManager->persist($article);
                $this->entityManager->flush();

                $hasParent = $article->getParentArticle() !== null;
                $parentTitle = $hasParent ? $article->getParentArticle()->getTitle() : 'None';
                
                $io->success("✅ Article created successfully");
                $io->text("Title: " . $article->getTitle());
                $io->text("Parent: " . $parentTitle);
                
            } catch (\Exception $e) {
                $io->error("❌ Failed: " . $e->getMessage());
            }
        }

        // Test with valid parent from previous test
        $io->section("Testing: Valid parent reference");
        
        $parentArticle = $this->entityManager->getRepository(Article::class)
            ->findOneBy(['title' => 'Parent Article for Testing']);
            
        if ($parentArticle) {
            try {
                $validTestData = [
                    'title' => 'Valid Child Article Test',
                    'content' => 'This should work correctly',
                    'status' => 'published',
                    'articleType' => 'answer',
                    'parentArticle' => '/api/articles/' . $parentArticle->getSlug()
                ];

                $article = $this->serializer->deserialize(
                    json_encode($validTestData),
                    Article::class,
                    'json',
                    ['groups' => ['article:write']]
                );

                $article->setAuthor($user);
                $this->entityManager->persist($article);
                $this->entityManager->flush();

                $io->success("✅ Valid parent reference works correctly");
                $io->text("Child: " . $article->getTitle());
                $io->text("Parent: " . $article->getParentArticle()->getTitle());
                
            } catch (\Exception $e) {
                $io->error("❌ Unexpected failure: " . $e->getMessage());
            }
        }

        $io->success('All edge case tests completed!');
        
        return Command::SUCCESS;
    }
}