<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Tells the error monitor who was signed in when an error happened: the staff id, the church id and the role,
 * never a name, phone number or email (agreement section 27). With no monitor configured this does nothing.
 */
class AddErrorMonitoringContext
{
    /**
     * Adds the staff member's ids to the monitor's scope for this request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            configureScope(function (Scope $scope) use ($user): void {
                $scope->setUser(['id' => $user->getKey()]);
                $scope->setTag('church_id', (string) $user->church_id);
                $scope->setTag('role', (string) $user->getRoleNames()->first());
            });
        }

        return $next($request);
    }
}
