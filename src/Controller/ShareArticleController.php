<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ArticleRepository;
use App\Entity\Article;

class ShareArticleController extends AbstractController
{
    #[Route('/share/articles/{id}', name: 'share_article')]
    public function __invoke(
        string $id,
        ArticleRepository $articleRepository
    ): Response {
        /** @var Article  $article */
        $article = $articleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException();
        }

        // For comments, use the root article's title
        $title = $article->getArticleType() === \App\Enum\ArticleType::COMMENT && $article->getRootArticle()
            ? ($article->getRootArticle()->getTitle() ?: $article->getRootArticle()->getTitleMg())
            : ($article->getTitle() ?: $article->getTitleMg());

        return $this->render('share/article.html.twig', [
            'title' => $title,
            'description' => $article->getExcerpt(),
            'redirectUrl' => sprintf(
                '%s/articles/%s',
                $_ENV['FRONTEND_URL'],
                $article->getSlug()
            ),
        ]);
    }
}
