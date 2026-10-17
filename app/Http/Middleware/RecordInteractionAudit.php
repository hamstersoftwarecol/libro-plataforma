<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordInteractionAudit
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Record meaningful user and admin web page interactions.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->record($request, $response);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('POST')) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if (
            $request->is(
                '_boost/*',
                'up',
                'admin/auditoria/feed*',
                'leer/*/pagina/*',
                'leer/*/texto/*',
                'leer/*/capitulo/*',
                'leer/*/progreso*',
                'storage/*',
                'livewire/*'
            )
        ) {
            return false;
        }

        if ($request->ajax() && ! $request->is('admin/*')) {
            return false;
        }

        return true;
    }

    private function record(Request $request, Response $response): void
    {
        $user = $request->user();
        $isAdmin = $request->is('admin*') || ($user && $user->isAdmin());

        $action = $isAdmin ? 'interaction.admin' : 'interaction.user';
        $routeName = $request->route()?->getName() ?: $request->path();

        $this->auditLogger->log(
            action: $action,
            auditable: null,
            metadata: [
                'path' => '/'.$request->path(),
                'route' => $routeName,
                'method' => $request->method(),
                'role' => $user?->role?->value ?? 'guest',
                'status' => $response->getStatusCode(),
            ],
            user: $user
        );
    }
}
