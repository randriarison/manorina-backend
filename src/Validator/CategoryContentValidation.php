<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Custom validation constraint for Category entity to ensure proper multilingual content.
 * 
 * Validation Rules:
 * A - At least one of the two attributes $name and $nameMg must be valid (non-empty after trim)
 * B - At least one of the two attributes $description and $descriptionMg must be valid (non-empty after trim)
 */
#[\Attribute]
class CategoryContentValidation extends Constraint
{
    public string $messageNameRequired = 'At least one of "name" or "nameMg" must be provided and non-empty.';
    public string $messageDescriptionRequired = 'At least one of "description" or "descriptionMg" must be provided and non-empty.';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}