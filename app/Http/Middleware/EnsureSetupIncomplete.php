<?php

namespace App\Http\Middleware;

use App\Services\Setup\SetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupIncomplete
{
    public function __construct(
        protected SetupService $setup,
    ) {}

    /**
     * Prevent the wizard from being reached once setup is complete.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->setup->isComplete()) {
            return $next($request);
        }

        return $request->user()
            ? to_route('dashboard')
            : to_route('login');
    }
}
