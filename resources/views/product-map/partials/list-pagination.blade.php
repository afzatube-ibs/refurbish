@php

    $pagination = $listingPagination ?? [];

    $currentPage = max(1, (int) ($pagination['page'] ?? 1));

    $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));

    $totalRecords = (int) ($pagination['total'] ?? 0);

    $hasPrevious = ! empty($pagination['has_previous']);

    $hasNext = ! empty($pagination['has_next']);

    $paginationMeta = 'Page '.$currentPage.' of '.$totalPages.' · '.$totalRecords.' records';

    $placement = $placement ?? 'footer';

    $pageQuery = $listingQuery ?? [];

    $prevParams = array_merge($pageQuery, ['page' => max(1, $currentPage - 1)]);

    $nextParams = array_merge($pageQuery, ['page' => $currentPage + 1]);

    if ($currentPage <= 1) {

        unset($prevParams['page']);

    }

@endphp



@if ($placement === 'top')

    <div class="product-map-list-toolbar">

        <span class="product-map-pagination-meta">{{ $paginationMeta }}</span>

    </div>

@else

    <div class="product-map-list-footer">

        <span class="product-map-pagination-meta">{{ $paginationMeta }}</span>

        <div class="product-map-pagination-actions">

            @if ($hasPrevious)

                <a href="{{ route('product-map.index', $prevParams) }}" class="product-map-page-btn">Previous</a>

            @else

                <span class="product-map-page-btn is-disabled" aria-disabled="true">Previous</span>

            @endif

            @if ($hasNext)

                <a href="{{ route('product-map.index', $nextParams) }}" class="product-map-page-btn">Next</a>

            @else

                <span class="product-map-page-btn is-disabled" aria-disabled="true">Next</span>

            @endif

        </div>

    </div>

@endif

