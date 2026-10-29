<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Earliest date of birth
    |--------------------------------------------------------------------------
    | A date of birth before this date is refused as a typing mistake. The React app mirrors it in
    | resources/js/constants/dates.ts.
    */
    'earliest_birth_date' => '1900-01-01',

    /*
    |--------------------------------------------------------------------------
    | Youth age limit
    |--------------------------------------------------------------------------
    | A member younger than this many years counts as youth, for example in the youth
    | pastor's view of the directory (D-031). A church can set its own value in its
    | settings under "youth_age_limit"; this is the default.
    */
    'youth_age_limit' => 25,

    /*
    |--------------------------------------------------------------------------
    | Member numbers
    |--------------------------------------------------------------------------
    | A member number is the church prefix, a dash and the sequence padded to this many digits
    | (GRC-0042). The sequence keeps growing past this width instead of wrapping (D-030).
    */
    'number_digits' => 4,

    /*
    |--------------------------------------------------------------------------
    | Profile photos
    |--------------------------------------------------------------------------
    | What an upload may be (docs/08, Upload Member Photo) and how it is stored. Every upload is re-encoded
    | to a square JPEG of `photo_size` pixels, which also removes hidden camera data (D-032).
    */
    'photo_types' => ['jpg', 'jpeg', 'png'],
    'photo_max_kilobytes' => 5 * 1024,
    'photo_max_pixels' => 4096,
    'photo_size' => 512,
    'photo_quality' => 82,
    'photo_file_name' => 'photo.jpg',
    'photo_link_minutes' => 60,

];
