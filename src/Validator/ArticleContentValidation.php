<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ArticleContentValidation extends Constraint
{
    public string $titleRequiredMessage = 'For this article type, at least one title field (title or titleMg) must be provided and contain more than 4 characters.';
    public string $contentRequiredMessage = 'At least one content field (content or contentMg) must be provided and contain more than 4 characters.';
    
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}