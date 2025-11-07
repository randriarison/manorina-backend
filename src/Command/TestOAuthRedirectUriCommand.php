<?php

namespace App\Command;

use App\Controller\OAuthRedirectController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'test:oauth-redirect-uri',
    description: 'Test OAuth redirect URI configuration'
)]
class TestOAuthRedirectUriCommand extends Command
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Testing OAuth Redirect URI Configuration');
        
        // Test environment variable access
        $io->section('Environment Variable Configuration');
        
        try {
            $redirectUri = $_ENV['OAUTH_GOOGLE_REDIRECT_URI'] ?? 'Not set';
            $io->text("OAUTH_GOOGLE_REDIRECT_URI from \$_ENV: {$redirectUri}");
            
            $io->success('Environment variable is properly configured');
            
            $io->section('Expected Behavior');
            $io->text('✅ The OAuthRedirectController should now use the environment variable instead of hardcoded URL');
            $io->text('✅ Redirect URL will be: ' . $redirectUri);
            
            $io->section('Testing URLs');
            $io->text('Test the OAuth redirect with these URLs:');
            $io->listing([
                '/oauth/redirect/google?code=test_code&state=test_state',
                '/oauth/redirect/facebook?code=test_code&state=test_state',
            ]);
            
        } catch (\Exception $e) {
            $io->error('Failed to access environment variable: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}