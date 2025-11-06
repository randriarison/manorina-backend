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
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

#[AsCommand(
    name: 'test:only-mine-endpoint',
    description: 'Test the /api/articles/only-mine endpoint functionality'
)]
class TestOnlyMineEndpointCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create test users if they don't exist
        $testUser1 = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'author1@example.com']);
        
        if (!$testUser1) {
            $testUser1 = new User();
            $testUser1->setEmail('author1@example.com');
            $testUser1->setRoles(['ROLE_USER']);
            $this->entityManager->persist($testUser1);
        }

        $testUser2 = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'author2@example.com']);
        
        if (!$testUser2) {
            $testUser2 = new User();
            $testUser2->setEmail('author2@example.com');
            $testUser2->setRoles(['ROLE_USER']);
            $this->entityManager->persist($testUser2);
        }

        // Create test articles for both users
        $article1 = new Article();
        $article1->setTitle('Article by Author 1');
        $article1->setContent('This is content written by author1@example.com');
        $article1->setStatus(ArticleStatus::PUBLISHED);
        $article1->setArticleType(ArticleType::SIMPLE);
        $article1->setAuthor($testUser1);
        $this->entityManager->persist($article1);

        $article2 = new Article();
        $article2->setTitle('Another Article by Author 1');
        $article2->setContent('Another article by author1@example.com');
        $article2->setStatus(ArticleStatus::PUBLISHED);
        $article2->setArticleType(ArticleType::SIMPLE);
        $article2->setAuthor($testUser1);
        $this->entityManager->persist($article2);

        $article3 = new Article();
        $article3->setTitle('Article by Author 2');
        $article3->setContent('This is content written by author2@example.com');
        $article3->setStatus(ArticleStatus::PUBLISHED);
        $article3->setArticleType(ArticleType::SIMPLE);
        $article3->setAuthor($testUser2);
        $this->entityManager->persist($article3);

        $this->entityManager->flush();

        // Generate JWT tokens for both users
        $token1 = $this->jwtManager->create($testUser1);
        $token2 = $this->jwtManager->create($testUser2);

        $io->success('Test data created successfully!');
        
        $io->section('Test Users and Articles Created:');
        $io->text([
            'Author 1: author1@example.com (2 articles)',
            'Author 2: author2@example.com (1 article)',
        ]);

        $io->section('JWT Tokens Generated:');
        $io->text('Token for author1@example.com:');
        $io->text($token1);
        $io->newLine();
        
        $io->text('Token for author2@example.com:');
        $io->text($token2);
        $io->newLine();

        $io->section('Test Scenarios:');
        $io->text([
            '1. Test without authentication (should return 401):',
            '   curl -X GET "http://localhost/api/articles/only-mine"',
            '',
            '2. Test with author1 token (should return 2 articles):',
            '   curl -X GET "http://localhost/api/articles/only-mine" \\',
            '        -H "Authorization: Bearer ' . $token1 . '"',
            '',
            '3. Test with author2 token (should return 1 article):',
            '   curl -X GET "http://localhost/api/articles/only-mine" \\',
            '        -H "Authorization: Bearer ' . $token2 . '"',
            '',
            '4. Compare with regular articles endpoint:',
            '   curl -X GET "http://localhost/api/articles"',
        ]);

        return Command::SUCCESS;
    }
}