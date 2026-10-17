<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    /**
     * Display live audit logs and metrics.
     */
    public function index(Request $request): View
    {
        Gate::authorize('view-audit-logs');

        $query = $this->filteredQuery($request);

        $logs = (clone $query)
            ->with(['user', 'auditable'])
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $todayStart = now()->startOfDay();

        $stats = [
            'total_today' => AuditLog::where('created_at', '>=', $todayStart)->count(),
            'active_users_today' => AuditLog::where('created_at', '>=', $todayStart)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'admin_events_today' => AuditLog::where('created_at', '>=', $todayStart)
                ->where(function (Builder $q) {
                    $q->where('action', 'interaction.admin')
                        ->orWhereHas('user', fn (Builder $uq) => $uq->where('role', Role::Admin->value));
                })->count(),
            'reader_events_today' => AuditLog::where('created_at', '>=', $todayStart)
                ->where(function (Builder $q) {
                    $q->where('action', 'like', 'reader.%')
                        ->orWhere('action', 'interaction.user')
                        ->orWhereHas('user', fn (Builder $uq) => $uq->where('role', Role::Reader->value));
                })->count(),
        ];

        return view('admin.audit.index', [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => [
                'role' => $request->query('role', 'all'),
                'category' => $request->query('category', 'all'),
                'search' => $request->query('search', ''),
                'date' => $request->query('date', ''),
            ],
            'categories' => [
                'all' => 'Todas las categorías',
                'reading' => 'Lectura',
                'auth' => 'Autenticación',
                'payment' => 'Pagos y Compras',
                'content' => 'Gestión de Contenido',
                'admin' => 'Interacciones Admin',
                'navigation' => 'Navegación Web',
                'system' => 'Sistema',
            ],
            'roles' => [
                'all' => 'Todos los roles',
                'admin' => 'Administradores',
                'reader' => 'Lectores',
                'guest' => 'Invitados / Sistema',
            ],
        ]);
    }

    /**
     * Real-time polling feed returning new logs since the given ID.
     */
    public function feed(Request $request): JsonResponse
    {
        Gate::authorize('view-audit-logs');

        $sinceId = max(0, (int) $request->query('since_id', 0));

        $query = $this->filteredQuery($request);

        if ($sinceId > 0) {
            $query->where('id', '>', $sinceId);
        }

        $newLogs = $query
            ->with(['user', 'auditable'])
            ->latest('id')
            ->limit(50)
            ->get();

        $items = $newLogs->map(function (AuditLog $log) {
            $user = $log->user;

            return [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => $log->actionLabel(),
                'category' => $log->category(),
                'badge_class' => $log->badgeClasses(),
                'user_name' => $user?->name ?? ($user?->email ?: 'Invitado / Sistema'),
                'user_email' => $user?->email,
                'user_role' => $user?->role?->label() ?? 'Invitado',
                'is_admin' => $user?->isAdmin() ?? str_starts_with($log->action, 'interaction.admin'),
                'ip_address' => $log->ip_address ?: '—',
                'user_agent' => $log->user_agent,
                'metadata' => $log->metadata ?: [],
                'created_at_human' => $log->created_at?->diffForHumans() ?: 'Reciente',
                'created_at_full' => $log->created_at?->translatedFormat('d/m/Y H:i:s') ?: '',
            ];
        });

        $lastId = $newLogs->max('id') ?? $sinceId;

        return response()->json([
            'items' => $items,
            'last_id' => $lastId,
            'count' => $items->count(),
        ]);
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('view-audit-logs');

        $query = $this->filteredQuery($request)->with(['user'])->latest('id')->limit(5000);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="auditoria-'.now()->format('Y-m-d-His').'.csv"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, ['ID', 'Fecha y Hora', 'Usuario', 'Correo', 'Rol', 'Acción', 'Categoría', 'IP', 'User Agent', 'Metadatos']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->toDateTimeString(),
                        $log->user?->name ?: '—',
                        $log->user?->email ?: '—',
                        $log->user?->role?->label() ?: 'Invitado',
                        $log->actionLabel(),
                        $log->category(),
                        $log->ip_address ?: '—',
                        $log->user_agent ?: '—',
                        $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Build filtered query according to incoming filters.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query();

        // Role filter
        $role = $request->query('role');
        if ($role === 'admin') {
            $query->where(function (Builder $q) {
                $q->whereHas('user', fn (Builder $uq) => $uq->where('role', Role::Admin->value))
                    ->orWhere('action', 'interaction.admin');
            });
        } elseif ($role === 'reader') {
            $query->where(function (Builder $q) {
                $q->whereHas('user', fn (Builder $uq) => $uq->where('role', Role::Reader->value))
                    ->orWhere('action', 'like', 'reader.%')
                    ->orWhere('action', 'interaction.user');
            });
        } elseif ($role === 'guest') {
            $query->whereNull('user_id');
        }

        // Category filter
        $category = $request->query('category');
        if ($category && $category !== 'all') {
            match ($category) {
                'auth' => $query->where('action', 'like', 'auth.%'),
                'reading' => $query->where('action', 'like', 'reader.%'),
                'payment' => $query->where(function (Builder $q) {
                    $q->where('action', 'like', 'payment.%')
                        ->orWhereIn('action', ['book.purchased', 'subscription.cancelled', 'subscription.resumed', 'subscription.courtesy_extended']);
                }),
                'content' => $query->where(function (Builder $q) {
                    $q->where('action', 'like', 'book.%')
                        ->orWhere('action', 'like', 'author.%')
                        ->orWhere('action', 'like', 'category.%')
                        ->orWhere('action', 'like', 'plan.%');
                }),
                'admin' => $query->where('action', 'interaction.admin'),
                'navigation' => $query->where('action', 'interaction.user'),
                'system' => $query->where(function (Builder $q) {
                    $q->where('action', 'like', 'settings.%')
                        ->orWhereIn('action', ['finance.exported', 'backup.created']);
                }),
                default => null,
            };
        }

        // Search query
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Date filter
        $date = $request->query('date');
        if ($date === 'today') {
            $query->where('created_at', '>=', now()->startOfDay());
        } elseif ($date === 'yesterday') {
            $query->whereBetween('created_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()]);
        } elseif ($date === '7days') {
            $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
        }

        return $query;
    }
}
