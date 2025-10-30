<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that COMMENT type articles have a parent
 */
#[\Attribute]
class CommentMustHaveParent extends Constraint
{
    public string $message = 'Articles of type COMMENT must have a parent article.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}