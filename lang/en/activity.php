<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Recent activity wording (D-068)
|--------------------------------------------------------------------------
| The plain sentences the home page's activity feed shows, kept out of the code so they can be reviewed and later translated
| (DD-009). They hold only what a sentence needs: a name, an amount and a fund, never a reason, a note, an email or a phone
| number. A record that no longer has a readable name is "A member" or "A staff member", never the erased marker.
*/
return [
    'a_member' => 'A member',
    'a_staff_member' => 'A staff member',
    'a_fund_that_was_removed' => 'a fund that was removed',

    'member_added' => ':name was added as a member.',
    'member_status_changed' => ':name is now :status.',
    'member_status_changed_unknown' => ':name\'s status was changed.',
    'gift_recorded' => 'A gift of :amount to :fund was recorded.',
    'gift_voided' => 'A gift of :amount to :fund was voided.',
    'gift_unknown' => 'A gift was :what.',
    'pledge_made' => 'A pledge of :amount to :fund was made.',
    'staff_invited' => ':name was invited as staff.',
    'staff_deactivated' => ':name was deactivated.',
    'staff_reactivated' => ':name was reactivated.',
    'settings_changed' => 'The church details or settings were changed.',
];
