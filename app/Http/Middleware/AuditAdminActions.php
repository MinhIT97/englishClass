<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach to admin routes to capture a baseline audit row for every
 * request — useful as a passive trail even when the controller
 * forgets to log manually. Detailed logs (e.g. "user X approved
 * user Y") are still the responsibility of the controller via
 * AuditLogger::log().
 *
 * Only writes when the request actually mutates state (POST/PUT/PATCH/
 * DELETE) so we don't flood the table with read-only GETs.
 */
class AuditAdminActions
{
    public function __construct(protected AuditLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldAudit($request)) {
            return $response;
        }

        $this->logger->log(
            action: 'admin.route.' . strtolower($request->method()),
            target: null,
            metadata: [
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
            ],
        );

        return $response;
    }

    private function shouldAudit(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}