<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Article processor to ensure author is set from authenticated user
 */
class ArticleProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Article) {
            // Ensure author is set from authenticated user for new articles
            $user = $this->security->getUser();
            if ($user && method_exists($data, 'getAuthor') && method_exists($data, 'setAuthor')) {
                // Only set author if it's not already set (for new entities)
                try {
                    $currentAuthor = $data->getAuthor();
                    if (!$currentAuthor) {
                        $data->setAuthor($user);
                    }
                } catch (\Error $e) {
                    // Author is not set yet (new entity), so set it
                    $data->setAuthor($user);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}