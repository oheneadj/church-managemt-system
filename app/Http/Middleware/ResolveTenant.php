<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Church;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which church a request belongs to from its host and binds it as 'current.church'.
 * I return 404 for an unknown host so nobody can tell which churches exist.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $church = $this->findChurch($request->getHost());

        if ($church === null || ! $church->is_active) {
            abort(404);
        }

        app()->instance('current.church', $church);

        return $next($request);
    }

    private function findChurch(string $host): ?Church
    {
        $suffix = '.'.config('tenancy.base_domain');

        if (str_ends_with($host, $suffix)) {
            $slug = substr($host, 0, -strlen($suffix));

            return Church::query()->where('slug', $slug)->first();
        }

        return Church::query()->where('custom_domain', $host)->first();
    }
}
