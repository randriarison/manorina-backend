<?php

namespace App\EventSubscriber;

use App\Entity\Article;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ArticleOwnerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['addDraftFields', -1000],
        ];
    }

    public function addDraftFields(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only process successful GET requests
        if (!$request->isMethod(Request::METHOD_GET)) {
            return;
        }

        // Check if user is authenticated
        $currentUser = $this->security->getUser();
        if (!$currentUser) {
            return;
        }

        // Get the original Article object from request attributes
        $article = $request->attributes->get('data');
        if (!$article instanceof Article) {
            return;
        }

        // Check if current user is the author
        if ($article->getAuthor() !== $currentUser) {
            return;
        }

        $response = $event->getResponse();
        
        // Only process successful JSON responses
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        $contentType = $response->headers->get('Content-Type');
        if (!$contentType || !str_contains($contentType, 'application/ld+json')) {
            return;
        }

        // Decode JSON, add draft fields, and re-encode
        try {
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            if (!is_array($data)) {
                return;
            }

            // Add draft fields to the response
            if ($article->getDraftContent() !== null) {
                $data['draftContent'] = $article->getDraftContent();
            }
            if ($article->getDraftContentMg() !== null) {
                $data['draftContentMg'] = $article->getDraftContentMg();
            }
            if ($article->getDraftContentEn() !== null) {
                $data['draftContentEn'] = $article->getDraftContentEn();
            }
            
            $newContent = json_encode($data);
            $response->setContent($newContent);
            
            // Update Content-Length header
            $response->headers->set('Content-Length', strlen($newContent));
            
            // Remove ETag as content has changed
            $response->headers->remove('ETag');
        } catch (\Exception $e) {
            // If there's any error, just return without modifying the response
            return;
        }
    }
}
