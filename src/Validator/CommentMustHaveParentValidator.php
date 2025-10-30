<?php

namespace App\Validator;

use App\Entity\Article;
use App\Enum\ArticleType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CommentMustHaveParentValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof CommentMustHaveParent) {
            throw new UnexpectedTypeException($constraint, CommentMustHaveParent::class);
        }

        if (!$value instanceof Article) {
            throw new UnexpectedTypeException($value, Article::class);
        }

        // If this is a COMMENT type article, it must have a parent
        if ($value->getArticleType() === ArticleType::COMMENT && $value->getParentArticle() === null) {
            $this->context->buildViolation($constraint->message)
                ->atPath('parentArticle')
                ->addViolation();
        }
    }
}