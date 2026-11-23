<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    /** The address the test dev-server marker names. Nothing listens there: a page test only needs the HTML. */
    private const FAKE_DEV_SERVER = 'http://127.0.0.1:5173';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useFakeViteDevServer();
    }

    /**
     * Lets a test render the app's HTML page, which asks Vite for its script and style tags. Vite answers from a built
     * manifest (public/build) or from a dev-server marker file (public/hot). CI has neither, because the backend job does
     * not build the front end, so every test would get a 500 on the page. A private marker file of our own makes the result
     * the same on every machine, whatever happens to be built or running there. A test that needs another state (a
     * missing marker, for instance) sets its own with Vite::useHotFile().
     */
    private function useFakeViteDevServer(): void
    {
        $marker = sys_get_temp_dir().'/chms-test-vite-hot';

        if (! is_file($marker)) {
            file_put_contents($marker, self::FAKE_DEV_SERVER."\n");
        }

        Vite::useHotFile($marker);
    }
}
