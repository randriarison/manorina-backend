<?php

namespace App\Validator;

use App\Entity\Article;
use App\Enum\ArticleType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ArticleContentValidationValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ArticleContentValidation) {
            throw new UnexpectedTypeException($constraint, ArticleContentValidation::class);
        }

        if (!$value instanceof Article) {
            return;
        }

        $articleType = $value->getArticleType();
        
        // Rule A: Title validation for specific article types
        if ($this->shouldValidateTitle($articleType)) {
            $this->validateTitleFields($value, $constraint);
        }
        
        // Rule B: Content validation (always applies)
        $this->validateContentFields($value, $constraint);
    }

    private function shouldValidateTitle(?ArticleType $articleType): bool
    {
        if ($articleType === null) {
            return true; // If no type is set, apply title validation
        }
        
        $exemptTypes = [
            ArticleType::COMMENT,
            ArticleType::ANSWER,
            ArticleType::PARAGRAPH
        ];
        
        return !in_array($articleType, $exemptTypes, true);
    }

    private function validateTitleFields(Article $article, ArticleContentValidation $constraint): void
    {
        $title = $article->getTitle();
        $titleMg = $article->getTitleMg();
        
        $isTitleValid = $this->isFieldValid($title);
        $isTitleMgValid = $this->isFieldValid($titleMg);
        
        if (!$isTitleValid && !$isTitleMgValid) {
            $this->context->buildViolation($constraint->titleRequiredMessage)
                ->atPath('title')
                ->addViolation();
        }
    }

    private function validateContentFields(Article $article, ArticleContentValidation $constraint): void
    {
        $content = $article->getContent();
        $contentMg = $article->getContentMg();
        
        $isContentValid = $this->isFieldValid($content);
        $isContentMgValid = $this->isFieldValid($contentMg);
        
        if (!$isContentValid && !$isContentMgValid) {
            $this->context->buildViolation($constraint->contentRequiredMessage)
                ->atPath('content')
                ->addViolation();
        }
    }

    private function isFieldValid(?string $field): bool
    {
        if ($field === null) {
            return false;
        }
        
        // Remove whitespace and check if empty
        $trimmedField = trim($field);
        if (empty($trimmedField)) {
            return false;
        }
        
        // Check if it contains more than 4 characters
        if (strlen($trimmedField) <= 4) {
            return false;
        }
        
        return true;
    }
}