@extends('layouts.app')

@section('title', 'Order Status Mapping — DropFlow SFM')
@section('page-title', 'Order Status Mapping')
@section('page-subtitle', 'Map OpenCart order statuses to SFM workflow states')

@section('content')
<div class="mb-4 flex flex-wrap items-center gap-3">
    <form method="POST" action="{{ route('settings.order-status-mapping.sync') }}">
        @csrf
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Fetch statuses from OpenCart
        </button>
    </form>
    <p class="text-sm text-slate-500">Statuses marked Ignore will not import orders.</p>
</div>

<form method="POST" action="{{ route('settings.order-status-mapping.update') }}">
    @csrf
    @method('PUT')

    <div class="bg-white rounded-lg border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm table-compact">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left font-medium text-slate-600">OC ID</th>
                        <th class="text-left font-medium text-slate-600">OpenCart Status</th>
                        <th class="text-left font-medium text-slate-600">SFM Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($mappings as $mapping)
                        <tr class="hover:bg-slate-50">
                            <td class="text-slate-500">{{ $mapping->source_status_id }}</td>
                            <td class="font-medium text-slate-900">{{ $mapping->source_status_name }}</td>
                            <td>
                                <input type="hidden" name="mappings[{{ $loop->index }}][source_status_id]" value="{{ $mapping->source_status_id }}">
                                <select name="mappings[{{ $loop->index }}][sfm_status]"
                                        class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 min-w-[160px]">
                                    @foreach (\App\Enums\SfmOrderStatus::forMappingDropdown() as $status)
                                        <option value="{{ $status->value }}"
                                            @selected(old("mappings.{$loop->index}.sfm_status", $mapping->sfm_status?->value ?? $mapping->sfm_status) === $status->value)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-slate-500 py-12">
                                No status mappings yet. Fetch statuses from OpenCart to begin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($mappings->isNotEmpty())
        <div class="mt-4">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Save mappings
            </button>
        </div>
    @endif
</form>
@endsection
