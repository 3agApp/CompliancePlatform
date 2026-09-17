<?php

namespace App\Http\Middleware;

use App\Concerns\ResolvesRouteOrganization;
use App\Enums\OrganizationType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationType
{
    use ResolvesRouteOrganization;

    /**
     * Handle an incoming request.
     *
     * A supplier organization has no supplier list and a distributor has no
     * distributor list, so the wrong side gets a 404 rather than a 403: the
     * resource does not exist for them, and the route set cannot be probed.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $organization = $this->routeOrganization($request);

        abort_if(
            $organization === null || $organization->type !== OrganizationType::tryFrom($type),
            404,
        );

        return $next($request);
    }
}
