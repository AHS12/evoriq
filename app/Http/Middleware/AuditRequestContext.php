<?php

namespace App\Http\Middleware;

use App\ActivityLog\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Boots the audit context for every web request so all audit rows written
 * during the request share one correlation id and carry request metadata.
 */
final class AuditRequestContext
{
    public function __construct(private readonly AuditContext $context)
    {
        //
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->context->start($request);

        return $next($request);
    }
}
