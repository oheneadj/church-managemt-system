<?php

declare(strict_types=1);

use App\Models\Church;

// D-022: the React shell answers every path that is not the API, the framework or a tool. A stored file's
// link (member photos) must reach the file route, not the shell.

beforeEach(function (): void {
    Church::factory()->create(['slug' => 'grace']);
});

it('serves the React shell for a screen path', function (): void {
    $this->get('http://grace.localhost/members')->assertOk()->assertViewIs('app');
});

it('sends a stored file path to the file route instead of the React shell', function (): void {
    // Without a valid signature the file route refuses; the shell would have answered 200.
    $this->get('http://grace.localhost/storage/churches/test/photo.jpg')->assertForbidden();
});
