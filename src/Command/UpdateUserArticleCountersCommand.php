<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-user-article-counters',
    description: 'Update article counters for all users based on their existing articles',
)]
class UpdateUserArticleCountersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Updating user article counters...');

        // Get all users
        $userRepository = $this->entityManager->getRepository(User::class);
        $users = $userRepository->findAll();

        if (empty($users)) {
            $io->warning('No users found in the database.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($users));

        $updatedUsers = 0;

        foreach ($users as $user) {
            // Count draft articles
            $draftCount = $this->countUserArticlesByStatus($user, ArticleStatus::DRAFT);
            
            // Count published articles  
            $publishedCount = $this->countUserArticlesByStatus($user, ArticleStatus::PUBLISHED);



            // Update the user's counters
            $user->setNbDraftArticles($draftCount);
            $user->setNbPublishedArticles($publishedCount);
            
            $this->entityManager->persist($user);
            $updatedUsers++;

            $io->progressAdvance();
        }

        // Flush all changes to database
        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf('Successfully updated article counters for %d users.', $updatedUsers));

        return Command::SUCCESS;
    }

    /**
     * Count articles for a user by status
     */
    private function countUserArticlesByStatus(User $user, ArticleStatus $status): int
    {
        try {
            // Use the repository's count method which handles UUIDs properly
            return $this->entityManager->getRepository(Article::class)->count([
                'author' => $user,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            // In case of error, return 0 to avoid breaking the application
            return 0;
        }
    }
}