@extends('layouts.app')

@section('title', 'Dashboard — DropFlow SFM')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Supplier fulfillment — phased build')

@section('content')
<div class="max-w-3xl space-y-6">
    {{-- Connection status --}}
    <div class="bg-white rounded-lg border border-slate-200 p-6">
        <h2 class="font-medium text-slate-900 mb-2">OpenCart Connection</h2>
        @if ($connectionSaved ?? false)
            <p class="text-sm text-emerald-700">Active connection saved for <span class="font-medium">{{ $connection->store_url }}</span></p>
            <p class="text-xs text-slate-500 mt-1">Supplier filter: {{ $connection->supplier_filter }}</p>
        @else
            <p class="text-sm text-amber-700">No active connection saved yet.</p>
            <a href="{{ route('connection.edit') }}" class="inline-block mt-3 text-sm text-slate-900 underline">Configure Connection →</a>
        @endif
    </div>

    {{-- Roadmap --}}
    <div class="bg-white rounded-lg border border-slate-200 p-6">
        <h2 class="font-medium text-slate-900 mb-4">Build Roadmap</h2>
        <ol class="space-y-4">
            @foreach ($roadmap as $step => $item)
                @php
                    $isActive = ($modules[$item['key']] ?? false) || $item['key'] === 'connection';
                    $isCurrent = ($item['key'] === 'product_map' && ($modules['product_map'] ?? false))
                        || ($item['key'] === 'connection' && ! ($modules['product_map'] ?? false));
                @endphp
                <li class="flex gap-4">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold
                        {{ $isCurrent ? 'bg-slate-900 text-white' : ($isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500') }}">
                        {{ $step }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ $item['label'] }}
                            @if ($isCurrent)
                                <span class="ml-2 text-xs font-normal text-slate-500">← current focus</span>
                            @elseif (! ($modules[$item['key']] ?? false) && $item['key'] !== 'connection')
                                <span class="ml-2 text-xs font-normal text-slate-400">waiting</span>
                            @endif
                        </p>
                        @if ($step === 1)
                            <p class="text-sm text-slate-500 mt-1">Live read-only OpenCart connection. Test all checks, then save.</p>
                        @elseif ($step === 2)
                            <p class="text-sm text-slate-500 mt-1">Step 2A: Live product preview — IBS Model master key, warehouse only, no import.</p>
                        @elseif ($step === 3)
                            <p class="text-sm text-slate-500 mt-1">Import supplier-assigned orders. Product must exist in Product Map.</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

        @if (auth()->user()->isAdmin() && ! ($connectionSaved ?? false))
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600">
            Configure your store connection in <a href="{{ route('connection.edit') }}" class="font-medium text-slate-900 underline">Connection</a>.
        </div>
        @endif
</div>
@endsection
