<?php

namespace App\Validator;

use App\Entity\Category;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CategoryContentValidationValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof CategoryContentValidation) {
            throw new UnexpectedTypeException($constraint, CategoryContentValidation::class);
        }

        if (!$value instanceof Category) {
            return;
        }

        // Rule A: At least one of name or nameMg must be valid
        if (!$this->isFieldValid($value->getName()) && !$this->isFieldValid($value->getNameMg())) {
            $this->context->buildViolation($constraint->messageNameRequired)
                ->addViolation();
        }

        // Rule B: At least one of description or descriptionMg must be valid
        if (!$this->isFieldValid($value->getDescription()) && !$this->isFieldValid($value->getDescriptionMg())) {
            $this->context->buildViolation($constraint->messageDescriptionRequired)
                ->addViolation();
        }
    }

    /**
     * Check if a field value is valid (not null and not empty after trim)
     */
    private function isFieldValid(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}