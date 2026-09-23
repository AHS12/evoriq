<?php

namespace App\Http\Middleware;

use App\Services\Setting\AppearanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    public function __construct(
        private readonly AppearanceService $appearance,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $this->resolve($request));

        return $next($request);
    }

    /**
     * Resolve the appearance for the request: defaults, then the cookie, then
     * the authenticated user's stored preferences.
     *
     * @return array{mode: string, theme: string, accent: string, accent_foreground: string, contrast: string}
     */
    private function resolve(Request $request): array
    {
        $resolved = array_merge(
            $this->appearance->defaults(),
            $this->fromCookie($request->cookie('appearance')),
        );

        $user = $request->user();

        if ($user !== null) {
            $resolved = array_merge($resolved, $this->appearance->stored($user));
        }

        return [
            'mode' => $resolved['mode'],
            'theme' => $resolved['theme'],
            'accent' => $resolved['accent'],
            'accent_foreground' => $this->appearance->accentForeground($resolved['accent']),
            'contrast' => $resolved['contrast'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fromCookie(mixed $cookie): array
    {
        if (! is_string($cookie) || $cookie === '') {
            return [];
        }

        parse_str($cookie, $parsed);

        return array_filter(
            array_intersect_key($parsed, array_flip(['mode', 'theme', 'accent', 'contrast'])),
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        );
    }
}
