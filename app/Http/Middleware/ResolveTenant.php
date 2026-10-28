<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Church;
use App\Repositories\ChurchRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which church a request belongs to from its host and binds it as 'current.church'.
 * I return 404 for an unknown host so nobody can tell which churches exist.
 */
class ResolveTenant
{
    /**
     * Takes the church repository.
     */
    public function __construct(private readonly ChurchRepositoryInterface $churches) {}

    /**
     * Finds the church for the request host and binds it, or answers 404.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $church = $this->findChurch($request->getHost());

        if ($church === null || ! $church->is_active) {
            abort(Response::HTTP_NOT_FOUND);
        }

        app()->instance('current.church', $church);
        // The church id is an opaque ULID, so it is safe in logs and lets support filter one church's activity.
        Context::add('church_id', $church->getKey());

        return $next($request);
    }

    /**
     * Looks the church up by subdomain slug, or by custom domain when the host is not on the base domain.
     */
    private function findChurch(string $host): ?Church
    {
        $suffix = '.'.config('tenancy.base_domain');

        if (str_ends_with($host, $suffix)) {
            $slug = substr($host, 0, -strlen($suffix));

            return $this->churches->findBySlug($slug);
        }

        return $this->churches->findByCustomDomain($host);
    }
}
