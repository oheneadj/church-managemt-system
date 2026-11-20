<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformAdminResource;
use App\Models\PlatformAdmin;
use Illuminate\Http\Request;

/**
 * Who the signed-in platform admin is, so the admin screens can restore a session after a page refresh (docs/08, Platform).
 */
class PlatformMeController extends Controller
{
    /**
     * Returns the signed-in admin.
     */
    public function __invoke(Request $request): PlatformAdminResource
    {
        $admin = $request->user('platform');

        return new PlatformAdminResource($admin instanceof PlatformAdmin ? $admin : abort(401));
    }
}
