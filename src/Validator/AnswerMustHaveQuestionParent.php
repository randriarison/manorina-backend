<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that ANSWER type articles have a parent of type QUESTION
 */
#[\Attribute]
class AnswerMustHaveQuestionParent extends Constraint
{
    public string $message = 'Articles of type ANSWER must have a parent article of type QUESTION.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}