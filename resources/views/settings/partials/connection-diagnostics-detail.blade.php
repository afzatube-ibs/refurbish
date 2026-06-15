@if ($testMeta ?? null)
    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Request summary</h3>
        <dl class="text-xs text-slate-600 space-y-1">
            <div class="flex justify-between gap-4"><dt>Request count</dt><dd>{{ $testMeta['requests_made'] ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt>Methods</dt><dd>{{ implode(', ', $testMeta['http_methods'] ?? []) }}</dd></div>
            <div class="flex justify-between gap-4"><dt>Timeout</dt><dd>{{ $testMeta['timeout_seconds'] ?? '—' }}s</dd></div>
        </dl>
    </div>
@endif

@if ($testDiagnostics ?? null)
    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Raw responses</h3>
        <pre class="text-xs bg-slate-900 text-slate-100 p-3 rounded overflow-x-auto max-h-48">{{ json_encode($testDiagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
@endif

@foreach ($results as $check)
    @if (! empty($check['detail']))
        <div>
            <h3 class="text-xs font-semibold text-slate-600 mb-1">{{ $check['label'] ?? $check['name'] }}</h3>
            <p class="text-xs text-slate-500">{{ $check['detail'] }}</p>
        </div>
    @endif
@endforeach

@if ($testSample ?? null)
    <div>
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Sample JSON</h3>
        <pre class="text-xs bg-slate-900 text-slate-100 p-3 rounded overflow-x-auto max-h-96">{{ json_encode($testSample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
@endif
