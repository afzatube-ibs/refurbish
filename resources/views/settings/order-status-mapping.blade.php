@extends('layouts.app')

@section('title', 'Order Status Mapping — DropFlow SFM')
@section('page-title', 'Order Status Mapping')
@section('page-subtitle', 'Map OpenCart queue statuses to IBS workflow — selected queue only affects sync')

@section('content')
@php
    /** @var \App\Services\OrderMap\OrderStatusMappingGuide $guide */
    $hasMappings = $activeMappings->isNotEmpty() || $referenceMappings->isNotEmpty();
@endphp

<div class="osm-layout">
    <div class="osm-main">
        <div class="osm-toolbar">
            <form method="POST" action="{{ route('settings.order-status-mapping.sync') }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Fetch statuses from OpenCart</button>
            </form>
            <p class="osm-toolbar-note">Only <strong>Queue Selected</strong> statuses can affect IBS order sync. Reference statuses stay inactive.</p>
        </div>

        @if ($hasMappings)
            <form method="POST"
                  action="{{ route('settings.order-status-mapping.update') }}"
                  id="orderStatusMappingForm"
                  class="osm-form">
                @csrf
                @method('PUT')

                <div class="osm-save-bar osm-save-bar-top">
                    <button type="submit" class="btn btn-primary" id="orderStatusMappingSaveTop">Save Mapping</button>
                </div>

                <section class="osm-section osm-section-active" aria-labelledby="osmActiveHeading">
                    <div class="osm-section-head">
                        <h2 id="osmActiveHeading" class="osm-section-title">Active Queue Statuses</h2>
                        <p class="osm-section-desc">OpenCart queue statuses marked <strong>Selected</strong>. These are the only mappings that can affect IBS order sync.</p>
                    </div>

                    <div class="osm-table-card">
                        <div class="osm-table-wrap">
                            <table class="data-table osm-table">
                                <thead>
                                    <tr>
                                        <th>OC ID</th>
                                        <th>OpenCart Status</th>
                                        <th>IBS Meaning</th>
                                        <th>Sync Behavior</th>
                                        <th>Last Saved</th>
                                        <th>IBS Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($activeMappings as $index => $mapping)
                                        @include('settings.partials.order-status-mapping-row', [
                                            'mapping' => $mapping,
                                            'index' => $index,
                                            'inactive' => false,
                                            'showBehavior' => true,
                                            'guide' => $guide,
                                        ])
                                    @empty
                                        <tr>
                                            <td colspan="6" class="osm-empty">No selected queue statuses yet. Fetch from OpenCart to begin.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                @if ($referenceMappings->isNotEmpty())
                    <section class="osm-section osm-section-reference" aria-labelledby="osmReferenceHeading">
                        <details class="osm-reference-details">
                            <summary class="osm-reference-summary" id="osmReferenceHeading">
                                <span class="osm-section-title">Other OpenCart Statuses</span>
                                <span class="osm-reference-count">{{ $referenceMappings->count() }} not selected — reference only</span>
                            </summary>
                            <p class="osm-section-desc">These statuses are not in the OpenCart order queue. They cannot affect sync and remain mapped to Ignore.</p>

                            <div class="osm-table-card osm-table-card-muted">
                                <div class="osm-table-wrap">
                                    <table class="data-table osm-table osm-table-muted">
                                        <thead>
                                            <tr>
                                                <th>OC ID</th>
                                                <th>OpenCart Status</th>
                                                <th>IBS Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($referenceMappings as $index => $mapping)
                                                @include('settings.partials.order-status-mapping-row', [
                                                    'mapping' => $mapping,
                                                    'index' => 'ref_'.$index,
                                                    'inactive' => true,
                                                    'showBehavior' => false,
                                                    'guide' => $guide,
                                                ])
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>
                    </section>
                @endif

                <div class="osm-save-bar osm-save-bar-bottom">
                    <button type="submit" class="btn btn-primary" id="orderStatusMappingSaveBottom">Save Mapping</button>
                </div>
            </form>
        @else
            <div class="osm-table-card">
                <p class="osm-empty">No status mappings yet. Fetch statuses from OpenCart to begin.</p>
            </div>
        @endif
    </div>

    <aside class="osm-aside" aria-label="Recommended mapping reference">
        <div class="osm-recommended-card">
            <h2 class="osm-recommended-title">Recommended current mapping</h2>
            <p class="osm-recommended-note">Reference only — use these pairings for live order workflow.</p>
            <ul class="osm-recommended-list">
                @foreach ($recommendedRows as $row)
                    <li>
                        <span class="osm-rec-oc">{{ $row['oc_id'] }} {{ $row['oc_name'] }}</span>
                        <span class="osm-rec-arrow">→</span>
                        <span class="osm-rec-ibs">{{ $row['ibs']->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>
</div>

<div class="osm-confirm-modal" id="osmConfirmModal" hidden aria-hidden="true">
    <div class="osm-confirm-backdrop" data-osm-confirm-cancel></div>
    <div class="osm-confirm-panel" role="dialog" aria-labelledby="osmConfirmTitle" aria-modal="true">
        <h3 id="osmConfirmTitle" class="osm-confirm-title">Confirm mapping changes</h3>
        <p class="osm-confirm-warning">You are changing live order workflow mapping. Wrong mapping can affect stock and order reports.</p>
        <ul class="osm-confirm-list" id="osmConfirmList"></ul>
        <div class="osm-confirm-actions">
            <button type="button" class="btn btn-primary btn-sm" id="osmConfirmProceed">Save mapping</button>
            <button type="button" class="btn btn-secondary btn-sm" data-osm-confirm-cancel>Cancel</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var helperTexts = @json(collect(\App\Enums\SfmOrderStatus::forMappingDropdown())->mapWithKeys(fn ($s) => [$s->value => $guide->helperText($s)])->all());
    var recommended = @json($recommendedJson ?? []);
    var form = document.getElementById('orderStatusMappingForm');
    if (!form) return;

    var modal = document.getElementById('osmConfirmModal');
    var confirmList = document.getElementById('osmConfirmList');
    var proceedBtn = document.getElementById('osmConfirmProceed');
    var allowSubmit = false;

    function updateHelper(select) {
        var row = select.closest('tr');
        var helper = row ? row.querySelector('[data-helper-for="' + select.getAttribute('data-mapping-id') + '"]') : null;
        if (helper) helper.textContent = helperTexts[select.value] || '';
    }

    form.querySelectorAll('.osm-status-select').forEach(function (select) {
        updateHelper(select);
        select.addEventListener('change', function () { updateHelper(select); });
    });

    function dangerousSelections() {
        var items = [];
        form.querySelectorAll('.osm-status-select').forEach(function (select) {
            var ocId = parseInt(select.getAttribute('data-oc-id'), 10);
            var rec = recommended[String(ocId)] || recommended[ocId];
            if (!rec) return;
            if (select.value !== rec) {
                items.push({
                    name: select.getAttribute('data-oc-name'),
                    recommended: rec,
                    selected: select.value
                });
            }
        });
        return items;
    }

    function labelFor(value) {
        var opt = form.querySelector('option[value="' + value + '"]');
        return opt ? opt.textContent.trim() : value;
    }

    form.addEventListener('submit', function (event) {
        if (allowSubmit) return;

        var dangerous = dangerousSelections();
        if (!dangerous.length) return;

        event.preventDefault();
        confirmList.innerHTML = dangerous.map(function (item) {
            return '<li><strong>' + item.name + '</strong>: recommended ' + labelFor(item.recommended) + ', selected ' + labelFor(item.selected) + '</li>';
        }).join('');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
    });

    proceedBtn.addEventListener('click', function () {
        allowSubmit = true;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        form.requestSubmit();
    });

    modal.querySelectorAll('[data-osm-confirm-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
        });
    });
})();
</script>
@endpush
