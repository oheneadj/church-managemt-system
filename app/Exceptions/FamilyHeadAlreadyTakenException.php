<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a second member tries to become head of a family that already has one (docs/03).
 */
class FamilyHeadAlreadyTakenException extends FieldRuleException
{
    /**
     * Blames the family role field.
     */
    public function __construct()
    {
        parent::__construct('family_role', 'This family already has a head of household.');
    }
}
