<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Services\Setting\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(
        private readonly LocaleService $locale,
    ) {}

    /**
     * Resolve and apply the request locale: the user's stored preference,
     * then the session (guest switch), then the global default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locale->storedForUser($request->user())
            ?? $this->fromSession($request)
            ?? $this->locale->default();

        App::setLocale($locale);

        return $next($request);
    }

    /**
     * The locale chosen in the session (guest switch).
     */
    private function fromSession(Request $request): ?string
    {
        $locale = $request->session()->get('locale');
        $case = AppLocale::tryFrom(is_string($locale) ? $locale : '');

        return $case?->value;
    }
}
