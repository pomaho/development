<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyAmoWidgetFramePolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $frameAncestors = trim((string) config('amo.widgets.frame_ancestors'));

        if ($frameAncestors !== '') {
            $response->headers->set('Content-Security-Policy', "frame-ancestors {$frameAncestors}");
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }
}
