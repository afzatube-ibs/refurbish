@php
    /** @var \App\Services\OrderMap\OrderStatusMappingGuide $guide */
    $statusOptions = \App\Enums\SfmOrderStatus::forMappingDropdown();
    $currentRole = $mapping->sync_role ?? \App\Enums\OrderSyncRole::recommendedFor($mapping->sfm_status ?? \App\Enums\SfmOrderStatus::Ignore);
@endphp

<tr class="osm-row @if ($inactive) osm-row-reference @endif">
    <td class="osm-col-id">{{ $mapping->source_status_id }}</td>
    <td class="osm-col-name">{{ $mapping->source_status_name }}</td>
    @if ($showBehavior)
        <td class="osm-col-queue">
            <span class="osm-queue-badge osm-queue-selected">Selected</span>
        </td>
        <td class="osm-col-ibs">
            <input type="hidden" name="mappings[{{ $index }}][id]" value="{{ $mapping->id }}">
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
                {{ $guide->helperText($mapping->sfm_status ?? \App\Enums\SfmOrderStatus::Ignore, $currentRole) }}
            </p>
        </td>
        <td class="osm-col-role">
            <select name="mappings[{{ $index }}][sync_role]" class="osm-select osm-role-select">
                @foreach ($syncRoleOptions as $role)
                    <option value="{{ $role->value }}"
                        @selected(old("mappings.{$index}.sync_role", $mapping->sync_role?->value ?? $currentRole->value) === $role->value)>
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>
        </td>
        <td class="osm-col-behavior">
            <span class="osm-behavior-text">{{ $guide->syncBehaviorLabel($mapping->sfm_status ?? \App\Enums\SfmOrderStatus::Ignore, $currentRole) }}</span>
        </td>
    @endif
    @if ($inactive)
        <td class="osm-col-ibs">
            <input type="hidden" name="mappings[{{ $index }}][id]" value="{{ $mapping->id }}">
            <input type="hidden" name="mappings[{{ $index }}][sfm_status]" value="ignore">
            <input type="hidden" name="mappings[{{ $index }}][sync_role]" value="ignore">
            <select class="osm-select osm-select-locked" disabled aria-readonly="true" tabindex="-1">
                <option selected>Ignore</option>
            </select>
        </td>
    @endif
</tr>
