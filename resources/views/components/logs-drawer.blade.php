@props(['drawer'])

@php
    $tabs = [
        'current' => 'Current Page',
        'connection' => 'Connection',
        'product-map' => 'Product Map',
        'system' => 'System',
    ];
    $defaultTab = $drawer['defaultTab'] ?? 'current';
    $openOnLoad = $drawer['openOnLoad'] ?? false;
@endphp

<button type="button"
        id="logs-drawer-open"
        class="header-action-btn header-action-btn--secondary"
        aria-controls="logs-drawer"
        aria-expanded="false">
    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    Logs
</button>

<div id="logs-drawer" class="logs-drawer {{ $openOnLoad ? '' : 'hidden' }}" aria-hidden="{{ $openOnLoad ? 'false' : 'true' }}">
    <div class="logs-drawer-backdrop" id="logs-drawer-backdrop"></div>

    <aside class="logs-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="logs-drawer-title">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 id="logs-drawer-title" class="text-base font-semibold text-slate-900">Logs &amp; Diagnostics</h2>
                <p class="text-xs text-slate-500 mt-0.5">Status, errors, and debug details</p>
            </div>
            <button type="button" id="logs-drawer-close" class="rounded-md p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100" aria-label="Close logs panel">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="border-b border-slate-200 px-3">
            <nav class="flex gap-1 overflow-x-auto" role="tablist" aria-label="Log categories">
                @foreach ($tabs as $key => $label)
                    <button type="button"
                            class="logs-drawer-tab {{ $key === $defaultTab ? 'is-active' : '' }}"
                            data-logs-tab="{{ $key }}"
                            role="tab"
                            aria-selected="{{ $key === $defaultTab ? 'true' : 'false' }}"
                            aria-controls="logs-panel-{{ $key }}">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="logs-drawer-body">
            @foreach ($tabs as $key => $label)
                @php
                    $tabKey = match ($key) {
                        'current' => 'currentPage',
                        'connection' => 'connection',
                        'product-map' => 'productMap',
                        'system' => 'system',
                    };
                    $tab = $drawer[$tabKey] ?? [];
                    $status = $tab['status'] ?? 'neutral';
                    $statusClass = match ($status) {
                        'ok' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                        'error' => 'bg-red-50 text-red-800 border-red-200',
                        default => 'bg-slate-50 text-slate-700 border-slate-200',
                    };
                @endphp
                <div id="logs-panel-{{ $key }}"
                     class="logs-drawer-panel-content {{ $key === $defaultTab ? '' : 'hidden' }}"
                     role="tabpanel"
                     data-logs-panel="{{ $key }}">
                    <div class="space-y-4">
                        <div class="rounded-md border px-3 py-2 text-sm {{ $statusClass }}">
                            {{ $tab['status_label'] ?? '—' }}
                        </div>

                        <dl class="text-sm space-y-2">
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">Last test / load time</dt>
                                <dd class="text-slate-800 text-right">{{ $tab['last_test_time'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 mb-0.5">Last error</dt>
                                <dd class="text-slate-800 {{ filled($tab['last_error'] ?? null) ? 'text-red-700' : '' }}">
                                    {{ $tab['last_error'] ?? '—' }}
                                </dd>
                            </div>
                        </dl>

                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Summary</h3>
                            <dl class="text-sm space-y-1.5">
                                @foreach (($tab['summary'] ?? []) as $summaryKey => $summaryValue)
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-slate-500 shrink-0">{{ $summaryKey }}</dt>
                                        <dd class="text-slate-800 text-right break-words">
                                            @if (is_array($summaryValue))
                                                {{ implode('; ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $summaryValue)) }}
                                            @else
                                                {{ $summaryValue }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <button type="button"
                                    class="logs-copy-btn rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    data-copy-target="logs-copy-{{ $key }}">
                                Copy
                            </button>
                            <textarea id="logs-copy-{{ $key }}" class="sr-only" readonly>{{ $tab['copy_json'] ?? '{}' }}</textarea>

                            @if (($tab['clear_route'] ?? null) && ($tab['has_logs'] ?? false))
                                <form method="POST" action="{{ route($tab['clear_route']) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                            title="Clears diagnostics, load traces, and debug entries only">
                                        Clear logs
                                    </button>
                                </form>
                            @endif

                            @if ($key === 'product-map' && ($tab['reset_route'] ?? null))
                                @php
                                    $preview = session('product_preview');
                                    $canResetProductMap = (is_array($preview) && ! empty($preview['products']))
                                        || is_array(session('product_map_pending_load'));
                                @endphp
                                @if ($canResetProductMap)
                                    <form method="POST"
                                          action="{{ route($tab['reset_route']) }}"
                                          class="inline"
                                          onsubmit="return window.confirm('Reset Product Map? This removes loaded products from your browser session. Product control history in the database is not deleted.');">
                                        @csrf
                                        <button type="submit"
                                                class="logs-reset-btn rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                            Reset Product Map
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>

                        <details class="rounded-md border border-slate-200 group">
                            <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-slate-700 hover:text-slate-900 list-none flex items-center justify-between">
                                <span>Advanced Details</span>
                                <svg class="w-4 h-4 text-slate-400 transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </summary>
                            <div class="px-4 pb-4 border-t border-slate-100">
                                <pre class="mt-3 text-xs bg-slate-900 text-slate-100 p-3 rounded overflow-x-auto max-h-64">{{ $tab['copy_json'] ?? '{}' }}</pre>
                            </div>
                        </details>
                    </div>
                </div>
            @endforeach
        </div>
    </aside>
</div>

@once
@push('scripts')
<script>
(function () {
    const drawer = document.getElementById('logs-drawer');
    const openBtn = document.getElementById('logs-drawer-open');
    const closeBtn = document.getElementById('logs-drawer-close');
    const backdrop = document.getElementById('logs-drawer-backdrop');

    if (!drawer || !openBtn) return;

    function setOpen(open) {
        drawer.classList.toggle('hidden', !open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('logs-drawer-open', open);
    }

    function activateTab(tabKey) {
        drawer.querySelectorAll('[data-logs-tab]').forEach(function (btn) {
            const active = btn.getAttribute('data-logs-tab') === tabKey;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        drawer.querySelectorAll('[data-logs-panel]').forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-logs-panel') !== tabKey);
        });
    }

    openBtn.addEventListener('click', function () { setOpen(true); });
    closeBtn?.addEventListener('click', function () { setOpen(false); });
    backdrop?.addEventListener('click', function () { setOpen(false); });

    drawer.querySelectorAll('[data-logs-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.getAttribute('data-logs-tab'));
        });
    });

    drawer.querySelectorAll('.logs-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const targetId = btn.getAttribute('data-copy-target');
            const source = targetId ? document.getElementById(targetId) : null;
            const text = source ? source.value : '';
            try {
                await navigator.clipboard.writeText(text);
                const original = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = original; }, 1500);
            } catch (e) {
                btn.textContent = 'Copy failed';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !drawer.classList.contains('hidden')) {
            setOpen(false);
        }
    });

    @if ($openOnLoad)
    setOpen(true);
    @endif
})();
</script>
@endpush
@endonce
