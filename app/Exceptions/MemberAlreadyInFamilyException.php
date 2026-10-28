<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a member who already belongs to a family would be moved silently into a new one (docs/03).
 */
class MemberAlreadyInFamilyException extends FieldRuleException
{
    /**
     * Blames the head member field.
     */
    public function __construct()
    {
        parent::__construct('head_member_id', 'This member already belongs to a family.');
    }
}
