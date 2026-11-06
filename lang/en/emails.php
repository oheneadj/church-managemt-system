<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Email wording
|--------------------------------------------------------------------------
| The words of the emails the platform sends, kept out of the code so they can be reviewed and later translated
| (DD-009). Plain language, no links to click: a code email must never look like a phishing message.
*/
return [
    'code' => [
        'subject' => 'Your :purpose for :church',
        'intro' => [
            'sign_in' => 'Use this code to finish signing in to :church.',
            'password_reset' => 'Use this code to choose a new password for :church.',
            'staff_invitation' => 'You have been invited to help run :church. Use this code to choose a password and sign in.',
        ],
        'valid_for' => 'The code works once and expires in :minutes minutes.',
        'ignore' => 'If you did not ask for this code, you can ignore this email. Nobody can sign in without it.',
        // What the message log keeps in place of the email: the code itself is never stored.
        'summary' => 'Email with a :purpose (the code is not stored)',
    ],
];
