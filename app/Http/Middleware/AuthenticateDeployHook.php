<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeployHook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.deploy.token', '');
        $providedToken = (string) $request->header('X-Deploy-Token', '');

        abort_unless(
            $expectedToken !== '' && hash_equals($expectedToken, $providedToken),
            Response::HTTP_FORBIDDEN
        );

        return $next($request);
    }
}
