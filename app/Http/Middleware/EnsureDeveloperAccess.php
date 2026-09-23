<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeveloperAccess
{
    /**
     * Only allow users holding the `developer.view` permission.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->hasPermissionTo('developer.view'),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
