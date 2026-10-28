<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Erased personal data (docs/05-data-safety.md, right to erasure)
    |--------------------------------------------------------------------------
    | The text that replaces a person's details when they are erased. The record itself stays, so money and
    | attendance records that point to it keep working.
    */
    'erased_marker' => '[deleted]',

    /*
    |--------------------------------------------------------------------------
    | Privacy policy version
    |--------------------------------------------------------------------------
    | Recorded with every consent, so it is known which policy a member agreed under. Change it when the
    | policy changes in a way that needs members to agree again.
    */
    'policy_version' => '1.0',

];
