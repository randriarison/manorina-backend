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
    name: 'test:parent-article-denormalization',
    description: 'Test parent article denormalization functionality'
)]
class TestParentArticleDenormalizationCommand extends Command
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

        $io->title('Testing Parent Article Denormalization');

        // Create test user if not exists
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'parent-article-test@example.com']);
        
        if (!$user) {
            $user = new User();
            $user->setEmail('parent-article-test@example.com');
            $user->setRoles(['ROLE_USER']);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        // Create a parent article first
        $parentArticle = new Article();
        $parentArticle->setTitle('Parent Article for Testing');
        $parentArticle->setContent('This is the parent article content.');
        $parentArticle->setStatus(ArticleStatus::PUBLISHED);
        $parentArticle->setArticleType(ArticleType::QUESTION);
        $parentArticle->setAuthor($user);

        $this->entityManager->persist($parentArticle);
        $this->entityManager->flush();

        $io->info("Created parent article with slug: " . $parentArticle->getSlug());

        // Now test denormalization with parentArticle reference
        $childArticleData = [
            'title' => 'Child Article with Parent Reference',
            'content' => 'This is a child article that references a parent.',
            'status' => 'published',
            'articleType' => 'answer',
            'parentArticle' => '/api/articles/' . $parentArticle->getSlug()
        ];

        $io->section('Testing Denormalization');
        $io->text('Child article data:');
        $io->text(json_encode($childArticleData, JSON_PRETTY_PRINT));

        try {
            // Test the denormalization
            $childArticle = $this->serializer->deserialize(
                json_encode($childArticleData),
                Article::class,
                'json',
                ['groups' => ['article:write']]
            );

            // Set required fields that aren't in denormalization
            $childArticle->setAuthor($user);

            $this->entityManager->persist($childArticle);
            $this->entityManager->flush();

            $io->success('Denormalization successful!');
            
            // Verify the relationship
            if ($childArticle->getParentArticle()) {
                $io->info('Parent article relationship established:');
                $io->text('Parent title: ' . $childArticle->getParentArticle()->getTitle());
                $io->text('Parent slug: ' . $childArticle->getParentArticle()->getSlug());
            } else {
                $io->error('Parent article relationship NOT established');
            }

            // Test the API response format
            $io->section('API Usage Example');
            $io->text('To create a child article via POST /api/articles, send:');
            $io->text(json_encode([
                'title' => 'My Child Article',
                'content' => 'Content for the child article',
                'status' => 'published',
                'articleType' => 'answer',
                'parentArticle' => '/api/articles/' . $parentArticle->getSlug()
            ], JSON_PRETTY_PRINT));

        } catch (\Exception $e) {
            $io->error('Denormalization failed: ' . $e->getMessage());
            $io->text('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}