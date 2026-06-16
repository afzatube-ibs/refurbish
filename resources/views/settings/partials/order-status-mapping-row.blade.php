@php
    /** @var \App\Services\OrderMap\OrderStatusMappingGuide $guide */
    $statusOptions = \App\Enums\SfmOrderStatus::forMappingDropdown();
@endphp

<tr class="osm-row @if ($inactive) osm-row-reference @endif">
    <td class="osm-col-id">{{ $mapping->source_status_id }}</td>
    <td class="osm-col-name">{{ $mapping->source_status_name }}</td>
    @if ($showBehavior)
        <td class="osm-col-meaning">
            @if ($mapping->sfm_status && $mapping->sfm_status !== \App\Enums\SfmOrderStatus::Ignore)
                <span class="osm-ibs-badge {{ $guide->ibsBadgeClass($mapping->sfm_status) }}">{{ $guide->ibsBadgeLabel($mapping->sfm_status) }}</span>
            @else
                <span class="osm-ibs-badge osm-badge-ignore">Inactive</span>
            @endif
        </td>
        <td class="osm-col-behavior">
            <span class="osm-behavior-text">{{ $guide->syncBehaviorLabel($mapping->sfm_status ?? \App\Enums\SfmOrderStatus::Ignore) }}</span>
        </td>
        <td class="osm-col-saved">{{ $mapping->updated_at?->format('d M Y H:i') ?? '—' }}</td>
    @endif
    <td class="osm-col-ibs">
        <input type="hidden" name="mappings[{{ $index }}][id]" value="{{ $mapping->id }}">
        @if ($inactive)
            <input type="hidden" name="mappings[{{ $index }}][sfm_status]" value="ignore">
            <select class="osm-select osm-select-locked" disabled aria-readonly="true" tabindex="-1">
                <option selected>Ignore</option>
            </select>
        @else
            <select name="mappings[{{ $index }}][sfm_status]"
                    class="osm-select osm-status-select"
                    data-mapping-id="{{ $mapping->id }}"
                    data-oc-id="{{ $mapping->source_status_id }}"
                    data-oc-name="{{ $mapping->source_status_name }}"
                    data-recommended="{{ $guide->recommendedFor($mapping)?->value ?? '' }}">
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}"
                        @selected(old("mappings.{$index}.sfm_status", $mapping->sfm_status?->value ?? $mapping->sfm_status) === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <p class="osm-helper-text" data-helper-for="{{ $mapping->id }}">
                {{ $guide->helperText($mapping->sfm_status ?? \App\Enums\SfmOrderStatus::Ignore) }}
            </p>
        @endif
    </td>
</tr>
