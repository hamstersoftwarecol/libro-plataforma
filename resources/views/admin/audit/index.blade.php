<x-admin-layout title="Auditoría en tiempo real" heading="Auditoría e Interacciones" description="Monitoreo de interacciones en vivo para usuarios y administradores.">
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <button id="live-toggle-btn" type="button" class="btn btn-secondary flex items-center gap-2">
                <span id="live-ping" class="relative flex size-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full size-2.5 bg-emerald-500"></span>
                </span>
                <span id="live-status-text" class="text-xs font-semibold">En vivo</span>
            </button>
            <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn btn-secondary">
                <x-icon name="download" class="size-4" />
                <span class="hidden sm:inline">Exportar CSV</span>
            </a>
        </div>
    </x-slot:actions>

    {{-- Métricas de interacción --}}
    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="stat">
            <div class="flex items-center justify-between">
                <p class="stat-label">Interacciones hoy</p>
                <x-icon name="trend" class="size-4 text-zinc-400" />
            </div>
            <p id="stat-total" class="stat-value text-2xl tabular-nums">{{ number_format($stats['total_today'], 0, ',', '.') }}</p>
            <p class="text-xs text-zinc-500">todas las acciones</p>
        </div>
        <div class="stat">
            <div class="flex items-center justify-between">
                <p class="stat-label">Usuarios activos hoy</p>
                <x-icon name="users" class="size-4 text-zinc-400" />
            </div>
            <p class="stat-value text-2xl tabular-nums">{{ number_format($stats['active_users_today'], 0, ',', '.') }}</p>
            <p class="text-xs text-zinc-500">con sesión iniciada</p>
        </div>
        <div class="stat ring-indigo-200 dark:ring-indigo-950/60">
            <div class="flex items-center justify-between">
                <p class="stat-label">Acciones de Admin</p>
                <x-icon name="shield" class="size-4 text-indigo-500" />
            </div>
            <p class="stat-value text-2xl text-indigo-600 dark:text-indigo-400 tabular-nums">{{ number_format($stats['admin_events_today'], 0, ',', '.') }}</p>
            <p class="text-xs text-zinc-500">gestión y cambios</p>
        </div>
        <div class="stat ring-sky-200 dark:ring-sky-950/60">
            <div class="flex items-center justify-between">
                <p class="stat-label">Acciones de Lectores</p>
                <x-icon name="book" class="size-4 text-sky-500" />
            </div>
            <p class="stat-value text-2xl text-sky-600 dark:text-sky-400 tabular-nums">{{ number_format($stats['reader_events_today'], 0, ',', '.') }}</p>
            <p class="text-xs text-zinc-500">lectura y compras</p>
        </div>
    </div>

    {{-- Filtros y búsqueda --}}
    <form method="GET" class="mt-6 flex flex-wrap items-center gap-2">
        <div class="relative min-w-64 flex-1 sm:max-w-xs">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-zinc-400" />
            <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Buscar usuario, IP, acción..." class="input pl-10">
        </div>

        <select name="role" class="input w-auto" onchange="this.form.submit()">
            @foreach ($roles as $key => $label)
                <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="category" class="input w-auto" onchange="this.form.submit()">
            @foreach ($categories as $key => $label)
                <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="flex flex-wrap items-center gap-1">
            @foreach (['' => 'Histórico', 'today' => 'Hoy', 'yesterday' => 'Ayer', '7days' => '7 días'] as $key => $label)
                <a href="{{ route('admin.audit.index', array_filter([...request()->except('page'), 'date' => $key])) }}"
                   @class(['chip', 'chip-active' => $filters['date'] === $key])>{{ $label }}</a>
            @endforeach
        </div>

        <button type="submit" class="btn btn-secondary">
            <x-icon name="filter" class="size-4" />
            <span>Filtrar</span>
        </button>

        @if ($filters['search'] || $filters['role'] !== 'all' || $filters['category'] !== 'all' || $filters['date'])
            <a href="{{ route('admin.audit.index') }}" class="btn btn-ghost text-xs">Limpiar filtros</a>
        @endif
    </form>

    {{-- Alerta de eventos nuevos en vivo --}}
    <div id="live-alert" class="mt-4 hidden items-center justify-between rounded-xl bg-brand-500/10 px-4 py-2 text-xs font-semibold text-brand-600 ring-1 ring-brand-500/20 dark:text-brand-400">
        <span class="flex items-center gap-2">
            <span class="size-2 rounded-full bg-brand-500 animate-pulse"></span>
            <span id="live-alert-text">Nuevas interacciones capturadas</span>
        </span>
        <button type="button" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })" class="link">Ir arriba ↑</button>
    </div>

    {{-- Tabla de interacciones --}}
    <div class="table-wrap mt-4">
        @if ($logs->isEmpty())
            <x-empty-state icon="shield" title="No hay registros de auditoría que coincidan con los filtros" />
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-40">Fecha y Hora</th>
                        <th>Actor / Usuario</th>
                        <th>Acción e Interacción</th>
                        <th>IP / Ubicación</th>
                        <th class="text-right">Detalles</th>
                    </tr>
                </thead>
                <tbody id="audit-table-body">
                    @foreach ($logs as $log)
                        @php
                            $user = $log->user;
                            $isAdmin = $user?->isAdmin() ?? str_starts_with($log->action, 'interaction.admin');
                        @endphp
                        <tr id="log-row-{{ $log->id }}" data-log-id="{{ $log->id }}" class="transition-colors duration-500">
                            <td class="whitespace-nowrap text-xs text-zinc-500">
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $log->created_at?->diffForHumans() }}</span>
                                <span class="block text-[10px] opacity-70">{{ $log->created_at?->translatedFormat('d/m/Y H:i:s') }}</span>
                            </td>
                            <td>
                                @if ($user)
                                    <div class="flex items-center gap-2.5">
                                        <x-avatar :user="$user" size="size-7" />
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.customers.show', $user) }}" class="truncate text-sm font-medium hover:text-brand-600 block">
                                                {{ $user->displayName() }}
                                            </a>
                                            <div class="flex items-center gap-1.5 text-xs text-zinc-400">
                                                <span @class([
                                                    'inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset',
                                                    'bg-indigo-500/10 text-indigo-500 ring-indigo-500/20' => $isAdmin,
                                                    'bg-zinc-500/10 text-zinc-400 ring-zinc-500/20' => ! $isAdmin,
                                                ])>
                                                    {{ $user->role->label() }}
                                                </span>
                                                <span class="truncate">{{ $user->email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2.5 text-zinc-400">
                                        <div class="grid size-7 place-items-center rounded-full bg-zinc-800 text-[11px] font-bold">?</div>
                                        <div>
                                            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Invitado / Sistema</span>
                                            @if (isset($log->metadata['email']))
                                                <span class="block text-xs font-mono text-zinc-500">{{ $log->metadata['email'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-lg border px-2 py-0.5 text-xs font-medium {{ $log->badgeClasses() }}">
                                        {{ $log->actionLabel() }}
                                    </span>
                                    <span class="font-mono text-[11px] text-zinc-400 opacity-80">{{ $log->action }}</span>
                                </div>
                                @if ($log->auditable)
                                    <p class="mt-1 text-xs text-zinc-500">
                                        Elemento: <span class="font-medium text-zinc-300">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</span>
                                        @if (isset($log->auditable->title)) · «{{ $log->auditable->title }}» @endif
                                        @if (isset($log->auditable->name)) · «{{ $log->auditable->name }}» @endif
                                    </p>
                                @elseif (isset($log->metadata['path']))
                                    <p class="mt-1 text-xs font-mono text-zinc-500 truncate max-w-md">
                                        {{ $log->metadata['method'] ?? 'GET' }} {{ $log->metadata['path'] }}
                                    </p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-xs text-zinc-400">
                                <span class="font-mono text-zinc-300">{{ $log->ip_address ?: '—' }}</span>
                                @if ($log->user_agent)
                                    <span class="block truncate max-w-44 text-[10px] text-zinc-500" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <button type="button"
                                        onclick="showAuditModal(@js($log->toArray()), @js($log->actionLabel()), @js($user?->email ?: 'Invitado'))"
                                        class="btn btn-ghost btn-sm text-xs font-semibold">
                                    <x-icon name="eye" class="size-4 text-zinc-400" />
                                    <span class="hidden sm:inline">Detalles</span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>

    {{-- Modal de inspección de metadatos --}}
    <div id="audit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/80 p-4 backdrop-blur-sm animate-fade-in" onclick="if(event.target === this) closeAuditModal()">
        <div class="relative w-full max-w-2xl rounded-3xl bg-zinc-900 p-6 shadow-2xl ring-1 ring-white/10 sm:p-8">
            <div class="flex items-center justify-between border-b border-zinc-800 pb-4">
                <div>
                    <h3 id="modal-action-title" class="text-lg font-semibold text-white">Detalle de Interacción</h3>
                    <p id="modal-actor-subtitle" class="text-xs text-zinc-400"></p>
                </div>
                <button type="button" onclick="closeAuditModal()" class="btn btn-ghost btn-sm p-1.5 text-zinc-400 hover:text-white">
                    <x-icon name="x" class="size-5" />
                </button>
            </div>

            <div class="mt-5 space-y-4 text-xs">
                <div class="grid grid-cols-2 gap-4 rounded-xl bg-zinc-950/60 p-4 ring-1 ring-white/5">
                    <div>
                        <span class="font-semibold text-zinc-500 uppercase tracking-wider text-[10px]">Identificador de acción</span>
                        <p id="modal-action-code" class="mt-0.5 font-mono text-zinc-200"></p>
                    </div>
                    <div>
                        <span class="font-semibold text-zinc-500 uppercase tracking-wider text-[10px]">Fecha exacta</span>
                        <p id="modal-timestamp" class="mt-0.5 text-zinc-200"></p>
                    </div>
                    <div>
                        <span class="font-semibold text-zinc-500 uppercase tracking-wider text-[10px]">Dirección IP</span>
                        <p id="modal-ip" class="mt-0.5 font-mono text-zinc-200"></p>
                    </div>
                    <div>
                        <span class="font-semibold text-zinc-500 uppercase tracking-wider text-[10px]">Agente de usuario</span>
                        <p id="modal-user-agent" class="mt-0.5 font-mono text-zinc-400 break-all"></p>
                    </div>
                </div>

                <div>
                    <span class="font-semibold text-zinc-400 block mb-1">Carga útil y Metadatos (JSON)</span>
                    <pre id="modal-metadata-json" class="max-h-60 overflow-y-auto rounded-xl bg-black/60 p-4 font-mono text-[11px] text-emerald-400 ring-1 ring-white/10"></pre>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeAuditModal()" class="btn btn-secondary">Cerrar</button>
            </div>
        </div>
    </div>

    {{-- Script de transmisión en tiempo real --}}
    <script>
        (() => {
            const feedUrl = @js(route('admin.audit.feed'));
            const currentParams = {
                role: @js($filters['role']),
                category: @js($filters['category']),
                search: @js($filters['search']),
                date: @js($filters['date']),
            };

            let isLive = true;
            let lastId = parseInt(document.querySelector('#audit-table-body tr')?.dataset.logId || '0', 10);
            let pollTimer = null;
            let capturedCount = 0;

            const toggleBtn = document.getElementById('live-toggle-btn');
            const livePing = document.getElementById('live-ping');
            const statusText = document.getElementById('live-status-text');
            const tableBody = document.getElementById('audit-table-body');
            const alertBox = document.getElementById('live-alert');
            const alertText = document.getElementById('live-alert-text');
            const statTotal = document.getElementById('stat-total');

            const setLive = (enable) => {
                isLive = enable;
                if (isLive) {
                    livePing.classList.remove('hidden');
                    statusText.textContent = 'En vivo';
                    statusText.classList.remove('text-zinc-500');
                    schedulePoll(1000);
                } else {
                    livePing.classList.add('hidden');
                    statusText.textContent = 'Pausado';
                    statusText.classList.add('text-zinc-500');
                    clearTimeout(pollTimer);
                }
            };

            toggleBtn?.addEventListener('click', () => setLive(!isLive));

            const escapeHtml = (text) => {
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return String(text || '').replace(/[&<>"']/g, m => map[m]);
            };

            const buildRowHtml = (item) => {
                const isAdminBadge = item.is_admin
                    ? '<span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset bg-indigo-500/10 text-indigo-500 ring-indigo-500/20">Administrador</span>'
                    : '<span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset bg-zinc-500/10 text-zinc-400 ring-zinc-500/20">' + escapeHtml(item.user_role) + '</span>';

                const userHtml = item.user_email
                    ? `<div class="flex items-center gap-2.5">
                         <div class="grid size-7 place-items-center rounded-full bg-brand-500/20 text-brand-400 text-xs font-semibold">${escapeHtml(item.user_name.charAt(0).toUpperCase())}</div>
                         <div class="min-w-0">
                             <span class="truncate text-sm font-medium block text-zinc-100">${escapeHtml(item.user_name)}</span>
                             <div class="flex items-center gap-1.5 text-xs text-zinc-400">
                                 ${isAdminBadge}
                                 <span class="truncate">${escapeHtml(item.user_email)}</span>
                             </div>
                         </div>
                       </div>`
                    : `<div class="flex items-center gap-2.5 text-zinc-400">
                         <div class="grid size-7 place-items-center rounded-full bg-zinc-800 text-[11px] font-bold">?</div>
                         <div><span class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Invitado / Sistema</span></div>
                       </div>`;

                const detailJson = JSON.stringify(item).replace(/"/g, '&quot;');

                return `
                    <tr id="log-row-${item.id}" data-log-id="${item.id}" class="bg-brand-500/10 transition-colors duration-1000 animate-fade-down">
                        <td class="whitespace-nowrap text-xs text-zinc-500">
                            <span class="font-medium text-brand-500 dark:text-brand-400">Ahora mismo</span>
                            <span class="block text-[10px] opacity-70">${escapeHtml(item.created_at_full)}</span>
                        </td>
                        <td>${userHtml}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-lg border px-2 py-0.5 text-xs font-medium ${escapeHtml(item.badge_class)}">
                                    ${escapeHtml(item.action_label)}
                                </span>
                                <span class="font-mono text-[11px] text-zinc-400 opacity-80">${escapeHtml(item.action)}</span>
                            </div>
                            ${item.metadata?.path ? `<p class="mt-1 text-xs font-mono text-zinc-500 truncate max-w-md">${escapeHtml(item.metadata.method || 'GET')} ${escapeHtml(item.metadata.path)}</p>` : ''}
                        </td>
                        <td class="whitespace-nowrap text-xs text-zinc-400">
                            <span class="font-mono text-zinc-300">${escapeHtml(item.ip_address)}</span>
                            ${item.user_agent ? `<span class="block truncate max-w-44 text-[10px] text-zinc-500" title="${escapeHtml(item.user_agent)}">${escapeHtml(item.user_agent)}</span>` : ''}
                        </td>
                        <td class="text-right">
                            <button type="button" onclick='showAuditModal(${detailJson}, "${escapeHtml(item.action_label)}", "${escapeHtml(item.user_email || 'Invitado')}")' class="btn btn-ghost btn-sm text-xs font-semibold">
                                <svg class="size-4 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <span class="hidden sm:inline">Detalles</span>
                            </button>
                        </td>
                    </tr>
                `;
            };

            const poll = async () => {
                if (!isLive || document.hidden) {
                    schedulePoll(3000);
                    return;
                }

                try {
                    const params = new URLSearchParams({
                        ...currentParams,
                        since_id: lastId,
                    });

                    const res = await fetch(`${feedUrl}?${params.toString()}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    });

                    if (res.ok) {
                        const data = await res.json();
                        if (data.items && data.items.length > 0) {
                            lastId = data.last_id;
                            capturedCount += data.items.length;

                            if (statTotal) {
                                const currentTotal = parseInt(statTotal.textContent.replace(/\D/g, '') || '0', 10);
                                statTotal.textContent = (currentTotal + data.items.length).toLocaleString('es-CO');
                            }

                            if (tableBody) {
                                for (const item of data.items.reverse()) {
                                    tableBody.insertAdjacentHTML('afterbegin', buildRowHtml(item));
                                    const newRow = document.getElementById(`log-row-${item.id}`);
                                    setTimeout(() => {
                                        newRow?.classList.remove('bg-brand-500/10');
                                    }, 2000);
                                }
                            }

                            if (alertBox && alertText) {
                                alertBox.classList.remove('hidden');
                                alertBox.classList.add('flex');
                                alertText.textContent = `+${capturedCount} nuevas interacciones capturadas en vivo`;
                            }
                        }
                    }
                } catch (e) {
                    console.warn('Error al consultar feed de auditoría:', e);
                }

                schedulePoll(3000);
            };

            const schedulePoll = (delay) => {
                clearTimeout(pollTimer);
                pollTimer = setTimeout(poll, delay);
            };

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && isLive) {
                    schedulePoll(500);
                }
            });

            schedulePoll(3000);
        })();

        // Control del Modal de Inspección
        function showAuditModal(log, actionLabel, actorEmail) {
            const modal = document.getElementById('audit-modal');
            document.getElementById('modal-action-title').textContent = actionLabel || log.action;
            document.getElementById('modal-actor-subtitle').textContent = `Actor: ${actorEmail || 'Invitado'}`;
            document.getElementById('modal-action-code').textContent = log.action;
            document.getElementById('modal-timestamp').textContent = log.created_at_full || log.created_at || '—';
            document.getElementById('modal-ip').textContent = log.ip_address || '—';
            document.getElementById('modal-user-agent').textContent = log.user_agent || 'No disponible';

            const metadata = log.metadata || {};
            document.getElementById('modal-metadata-json').textContent = Object.keys(metadata).length > 0
                ? JSON.stringify(metadata, null, 2)
                : 'Sin metadatos adicionales registrados.';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeAuditModal() {
            const modal = document.getElementById('audit-modal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }
    </script>
</x-admin-layout>
