<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a member is given a role in a family but no family.
 */
class FamilyRoleNeedsFamilyException extends FieldRuleException
{
    /**
     * Blames the family field.
     */
    public function __construct()
    {
        parent::__construct('family_id', 'Choose a family before giving a role in it.');
    }
}
