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

#[AsCommand(
    name: 'app:test-article-creation',
    description: 'Test if Article creation works with author assignment',
)]
class TestArticleCreationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Find a user to use as author
            $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
            if (!$user) {
                $output->writeln('<error>No user found in database</error>');
                return Command::FAILURE;
            }

            $output->writeln('Found user: ' . $user->getEmail());

            // Create a new Article
            $article = new Article();
            $output->writeln('Created Article instance');

            // Check if the methods exist
            if (method_exists($article, 'setAuthor')) {
                $output->writeln('Article has setAuthor method');
                $article->setAuthor($user);
                $output->writeln('Set author on article');
            } else {
                $output->writeln('<error>Article does NOT have setAuthor method</error>');
            }

            if (method_exists($article, 'getAuthor')) {
                $output->writeln('Article has getAuthor method');
                $author = $article->getAuthor();
                $output->writeln('Author is: ' . ($author ? $author->getEmail() : 'null'));
            } else {
                $output->writeln('<error>Article does NOT have getAuthor method</error>');
            }

            // Set required fields
            $article->setTitle('Test Article');
            $article->setContent('Test content');
            $article->setStatus(ArticleStatus::DRAFT);
            $article->setArticleType(ArticleType::SIMPLE);

            $output->writeln('Set all required fields');

            // Try to persist
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            $output->writeln('<info>Successfully created article with ID: ' . $article->getId() . '</info>');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>Trace: ' . $e->getTraceAsString() . '</error>');
            return Command::FAILURE;
        }
    }
}