@extends('layouts.app')

@section('title', 'Connection — DropFlow SFM')
@section('page-title', 'Connection')
@section('page-subtitle', 'Live Store Bridge')

@section('page-badge')
@php
    $badge = $badgeStatus ?? 'not_connected';
    [$badgeLabel, $badgeClass] = match ($badge) {
        'connected' => ['Connected', 'bg-emerald-100 text-emerald-800 border-emerald-200'],
        'needs_test' => ['Needs test', 'bg-amber-100 text-amber-800 border-amber-200'],
        default => ['Not connected', 'bg-slate-100 text-slate-600 border-slate-200'],
    };
@endphp
<span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold {{ $badgeClass }}">{{ $badgeLabel }}</span>
@endsection

@section('content')
@php
    $canSave = $canSave ?? false;
    $isEditing = $isEditing ?? true;
    $hasSavedConnection = $hasSavedConnection ?? false;
    $hasToken = filled($connection->api_token ?? null);
    $ibsDefaults = $ibsDefaults ?? \App\Services\OpenCart\IbsRouteResolver::defaultFormEndpoints();
    $results = $testResults ?? null;
    $allPassed = $results && collect($results)->every(function ($c) {
        if (! is_array($c)) {
            return (bool) $c;
        }
        if ($c['optional'] ?? false) {
            return true;
        }

        return $c['passed'] ?? false;
    });

    $display = [
        'store_url' => old('store_url', $connection->store_url ?? ''),
        'product_api_endpoint' => old('product_api_endpoint', $connection->product_api_endpoint ?: $ibsDefaults['product_api_endpoint']),
        'order_api_endpoint' => old('order_api_endpoint', $connection->order_api_endpoint ?: $ibsDefaults['order_api_endpoint']),
        'order_status_api_endpoint' => old('order_status_api_endpoint', $connection->order_status_api_endpoint ?: $ibsDefaults['order_status_api_endpoint']),
        'supplier_filter' => old('supplier_filter', $connection->supplier_filter ?? 'ex-a'),
        'is_active' => filter_var(old('is_active', $connection->is_active ?? false), FILTER_VALIDATE_BOOLEAN),
    ];

    $lastTestAt = $connection->last_connection_test_at;
    $lastTestStatus = $connection->last_connection_test_status;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-lg border border-slate-200">
            @if ($hasSavedConnection && ! $isEditing)
                <div class="p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-medium text-slate-900">Saved connection</h2>
                        <a href="{{ route('connection.edit', ['edit' => 1]) }}"
                           class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Edit Connection
                        </a>
                    </div>

                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                        <div>
                            <dt class="text-slate-500 mb-1">Store URL</dt>
                            <dd class="font-medium text-slate-900 break-all">{{ $display['store_url'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 mb-1">API Token</dt>
                            <dd class="font-medium text-slate-900 tracking-widest">{{ $hasToken ? '••••••••••••' : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 mb-1">Product API Endpoint</dt>
                            <dd class="text-slate-800 break-all">{{ $display['product_api_endpoint'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 mb-1">Order API Endpoint</dt>
                            <dd class="text-slate-800 break-all">{{ $display['order_api_endpoint'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 mb-1">Order Status API Endpoint</dt>
                            <dd class="text-slate-800 break-all">{{ $display['order_status_api_endpoint'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 mb-1">Supplier Filter</dt>
                            <dd class="text-slate-800">{{ $display['supplier_filter'] ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 mb-1">Connection Active</dt>
                            <dd class="text-slate-800">{{ $display['is_active'] ? 'Yes' : 'No' }}</dd>
                        </div>
                    </dl>
                </div>
            @else
                <form method="POST" action="{{ route('connection.update') }}" id="connection-form" class="p-6 space-y-5">
                    @csrf

                    @if ($hasSavedConnection)
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-slate-100">
                            <p class="text-sm text-slate-600">Editing connection settings</p>
                            <a href="{{ route('connection.edit', ['cancel' => 1]) }}"
                               class="text-sm text-slate-500 hover:text-slate-800 underline">
                                Cancel edit
                            </a>
                        </div>
                    @endif

                    <div>
                        <label for="store_url" class="block text-sm font-medium text-slate-700 mb-1">Store URL</label>
                        <input type="url" name="store_url" id="store_url" value="{{ $display['store_url'] }}" required
                               placeholder="https://store.lokkisona.com"
                               class="connection-field w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    <div>
                        <label for="api_token" class="block text-sm font-medium text-slate-700 mb-1">API Token</label>
                        @if ($hasToken)
                            <p class="mb-2 text-xs text-slate-500">Saved token: <span class="tracking-widest text-slate-700">••••••••••••</span> — leave blank to keep, or enter a new token to replace after test.</p>
                        @endif
                        <input type="password" name="api_token" id="api_token" value="{{ old('api_token') }}"
                               placeholder="{{ $hasToken ? 'Leave blank to keep current token' : 'Enter API token' }}"
                               autocomplete="new-password"
                               class="connection-field w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="product_api_endpoint" class="block text-sm font-medium text-slate-700 mb-1">Product API Endpoint</label>
                            <input type="text" name="product_api_endpoint" id="product_api_endpoint"
                                   value="{{ $display['product_api_endpoint'] }}" required
                                   placeholder="{{ $ibsDefaults['product_api_endpoint'] }}"
                                   class="connection-field w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div>
                            <label for="order_api_endpoint" class="block text-sm font-medium text-slate-700 mb-1">Order API Endpoint</label>
                            <input type="text" name="order_api_endpoint" id="order_api_endpoint"
                                   value="{{ $display['order_api_endpoint'] }}" required
                                   placeholder="{{ $ibsDefaults['order_api_endpoint'] }}"
                                   class="connection-field w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="order_status_api_endpoint" class="block text-sm font-medium text-slate-700 mb-1">Order Status API Endpoint</label>
                            <input type="text" name="order_status_api_endpoint" id="order_status_api_endpoint"
                                   value="{{ $display['order_status_api_endpoint'] }}" required
                                   placeholder="{{ $ibsDefaults['order_status_api_endpoint'] }}"
                                   class="connection-field w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div>
                            <label for="supplier_filter" class="block text-sm font-medium text-slate-700 mb-1">Supplier Filter</label>
                            <input type="text" name="supplier_filter" id="supplier_filter"
                                   value="{{ $display['supplier_filter'] }}" required
                                   class="connection-field w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               {{ $display['is_active'] ? 'checked' : '' }}
                               class="connection-field rounded border-slate-300 text-slate-600 focus:ring-slate-500">
                        <label for="is_active" class="text-sm text-slate-700">Connection Active</label>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100">
                        <button type="submit"
                                formaction="{{ route('connection.test') }}"
                                formmethod="POST"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Test Connection
                        </button>

                        <button type="submit"
                                id="save-connection-btn"
                                @disabled(! $canSave)
                                class="rounded-md px-4 py-2 text-sm font-medium text-white {{ $canSave ? 'bg-slate-900 hover:bg-slate-800' : 'bg-slate-300 cursor-not-allowed' }}"
                                title="{{ $canSave ? 'Save connection settings' : 'Complete a successful connection test first' }}">
                            Save Connection
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white rounded-lg border border-slate-200 p-6">
            <h2 class="font-medium text-slate-900 mb-1">Connection Status</h2>

            @if ($results)
                <ul class="mt-3">
                    @foreach ($results as $check)
                        <x-connection-check-item :check="$check" />
                    @endforeach
                </ul>

                @if ($allPassed)
                    <p class="mt-4 text-sm text-emerald-700">All required checks passed.@if ($isEditing) You may save your connection.@endif</p>
                @else
                    <p class="mt-4 text-sm text-slate-600">Review failed items, update your settings, and test again.</p>
                @endif
            @elseif ($lastTestAt && ! $isEditing)
                <p class="mt-2 text-sm text-slate-600">
                    Last tested {{ $lastTestAt->diffForHumans() }} —
                    @if ($lastTestStatus === 'passed')
                        <span class="text-emerald-700 font-medium">passed</span>
                    @else
                        <span class="text-red-700 font-medium">failed</span>
                    @endif
                </p>
                @if ($connection->last_connection_test_message)
                    <p class="mt-2 text-xs text-slate-500">{{ $connection->last_connection_test_message }}</p>
                @endif
            @else
                <p class="mt-2 text-sm text-slate-500">Run Test Connection to verify your store settings.</p>
            @endif
        </div>
    </div>
</div>

@if ($isEditing)
@push('scripts')
<script>
(function () {
    const form = document.getElementById('connection-form');
    const saveBtn = document.getElementById('save-connection-btn');
    let verified = @json($canSave);

    if (!form || !saveBtn) return;

    function setSaveEnabled(enabled) {
        saveBtn.disabled = !enabled;
        if (enabled) {
            saveBtn.classList.add('bg-slate-900', 'hover:bg-slate-800');
            saveBtn.classList.remove('bg-slate-300', 'cursor-not-allowed');
        } else {
            saveBtn.classList.remove('bg-slate-900', 'hover:bg-slate-800');
            saveBtn.classList.add('bg-slate-300', 'cursor-not-allowed');
        }
    }

    setSaveEnabled(verified);

    form.querySelectorAll('.connection-field').forEach(function (field) {
        field.addEventListener('input', function () {
            if (!verified) return;
            verified = false;
            setSaveEnabled(false);
        });
        field.addEventListener('change', function () {
            if (!verified) return;
            verified = false;
            setSaveEnabled(false);
        });
    });

    form.addEventListener('submit', function (event) {
        const submitter = event.submitter;
        if (submitter && submitter.id === 'save-connection-btn' && saveBtn.disabled) {
            event.preventDefault();
        }
    });
})();
</script>
@endpush
@endif
@endsection
