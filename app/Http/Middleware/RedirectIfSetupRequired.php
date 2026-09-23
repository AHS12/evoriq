<?php

namespace App\Http\Middleware;

use App\Services\Setup\SetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSetupRequired
{
    /**
     * Cached result for the current request (avoids repeated DB lookups).
     */
    protected ?bool $complete = null;

    public function __construct(
        protected SetupService $setup,
    ) {}

    /**
     * Redirect every request to the setup wizard until it is completed.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup.*') || $this->isAsset($request)) {
            return $next($request);
        }

        if ($this->complete ??= $this->setup->isComplete()) {
            return $next($request);
        }

        return to_route('setup.index');
    }

    /**
     * Static assets and health checks must stay reachable before setup.
     */
    protected function isAsset(Request $request): bool
    {
        return $request->is('build/*', 'storage/*', 'up', 'favicon.ico', 'robots.txt');
    }
}
