<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /** Accent colours the appearance settings offer; see use-accent.tsx. */
    private const ACCENTS = ['ocean', 'blue', 'violet', 'emerald', 'amber', 'rose', 'midnight'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        $accent = $request->cookie('accent');
        View::share('accent', in_array($accent, self::ACCENTS, true) ? $accent : 'ocean');

        return $next($request);
    }
}
