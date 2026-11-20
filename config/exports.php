<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CSV exports (docs/08, Exports; D-064)
|--------------------------------------------------------------------------
| An export is built by a worker, stored privately and handed out as a short-lived signed link. The file holds personal
| data, so the link stops working quickly and the file is deleted after a few days; the record of the export stays.
*/

return [

    // How long the emailed download link works.
    'link_minutes' => 60,

    // How long the private file is kept before the nightly clean-up deletes it.
    'file_days' => 7,

    // How many members are read from the database at a time while the file is built, so a large church does not fill memory.
    'chunk_size' => 500,

];
