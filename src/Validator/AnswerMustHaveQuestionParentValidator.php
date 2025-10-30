<?php

namespace App\Validator;

use App\Entity\Article;
use App\Enum\ArticleType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AnswerMustHaveQuestionParentValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AnswerMustHaveQuestionParent) {
            throw new UnexpectedTypeException($constraint, AnswerMustHaveQuestionParent::class);
        }

        if (!$value instanceof Article) {
            throw new UnexpectedTypeException($value, Article::class);
        }

        // If this is an ANSWER type article, it must have a parent of type QUESTION
        if ($value->getArticleType() === ArticleType::ANSWER) {
            $parent = $value->getParentArticle();
            
            if ($parent === null) {
                $this->context->buildViolation('Articles of type ANSWER must have a parent article.')
                    ->atPath('parentArticle')
                    ->addViolation();
                return;
            }

            if ($parent->getArticleType() !== ArticleType::QUESTION) {
                $this->context->buildViolation($constraint->message)
                    ->atPath('parentArticle')
                    ->addViolation();
            }
        }
    }
}