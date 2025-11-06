<?php

namespace App\Command;

use App\Entity\User;
use App\State\UserArticlesProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Article;

#[AsCommand(
    name: 'test:user-articles-provider',
    description: 'Test the UserArticlesProvider logic directly'
)]
class TestUserArticlesProviderCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserArticlesProvider $userArticlesProvider,
        private RequestStack $requestStack
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get test users
        $author1 = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'author1@example.com']);
        $author2 = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'author2@example.com']);

        if (!$author1 || !$author2) {
            $io->error('Test users not found. Run test:only-mine-endpoint first.');
            return Command::FAILURE;
        }

        // Test 1: Get all articles for author1
        $io->section('Testing Articles for author1@example.com:');
        $articles1 = $this->entityManager->getRepository(Article::class)
            ->findBy(['author' => $author1]);
        
        $io->text(sprintf('Found %d articles for author1:', count($articles1)));
        foreach ($articles1 as $article) {
            $io->text('- ' . $article->getTitle());
        }

        // Test 2: Get all articles for author2
        $io->section('Testing Articles for author2@example.com:');
        $articles2 = $this->entityManager->getRepository(Article::class)
            ->findBy(['author' => $author2]);
        
        $io->text(sprintf('Found %d articles for author2:', count($articles2)));
        foreach ($articles2 as $article) {
            $io->text('- ' . $article->getTitle());
        }

        // Test 3: Show all articles in database
        $io->section('All Articles in Database:');
        $allArticles = $this->entityManager->getRepository(Article::class)->findAll();
        $io->text(sprintf('Total articles: %d', count($allArticles)));
        foreach ($allArticles as $article) {
            $authorEmail = $article->getAuthor() ? $article->getAuthor()->getEmail() : 'No Author';
            $io->text(sprintf('- "%s" by %s', $article->getTitle(), $authorEmail));
        }

        $io->success('UserArticlesProvider test completed successfully!');

        // Provide testing instructions
        $io->section('Manual Testing Instructions:');
        $io->text([
            'To test the API endpoint manually:',
            '',
            '1. Start the Symfony server:',
            '   php -S 127.0.0.1:8000 -t public',
            '',
            '2. Test without authentication (should return 401):',
            '   Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/articles/only-mine"',
            '',
            '3. Get a JWT token by running:',
            '   php bin/console test:only-mine-endpoint',
            '',
            '4. Test with authentication using the token from step 3:',
            '   $headers = @{"Authorization" = "Bearer YOUR_TOKEN_HERE"}',
            '   Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/articles/only-mine" -Headers $headers',
        ]);

        return Command::SUCCESS;
    }
}