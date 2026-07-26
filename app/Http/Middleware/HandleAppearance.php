<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // O padrao para visitantes sem cookie e sempre claro — nunca segue o
        // tema do sistema operacional automaticamente. "system" so e usado
        // quando o proprio usuario escolhe essa opcao explicitamente.
        View::share('appearance', $request->cookie('appearance') ?? 'light');

        return $next($request);
    }
}
