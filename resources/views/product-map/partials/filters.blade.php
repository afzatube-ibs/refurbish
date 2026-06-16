@php
    $filters = $listingFilters ?? [];
    $categoryOptions = $filterCategories ?? [];
    $selectedCategory = (string) ($filters['category'] ?? '');
    $selectedType = (string) ($filters['type'] ?? 'all');
    $selectedHealth = (string) ($filters['health'] ?? 'all');
    $selectedPerPage = (int) ($filters['per_page'] ?? 20);
    $searchQuery = (string) ($filters['q'] ?? '');
@endphp

<div class="pm-filter-card">
    <div class="pm-filter-card-head">
        <h2 class="pm-filter-title">Search &amp; Filter</h2>
    </div>
    <form method="GET" action="{{ route('product-map.index') }}" class="pm-filter-form">
        <div class="pm-filter-row">
            <label class="pm-filter-field pm-filter-field--search">
                <span class="pm-filter-label">Search</span>
                <input type="search"
                       name="q"
                       value="{{ $searchQuery }}"
                       class="pm-filter-input"
                       placeholder="Model, ID, IBS model…"
                       autocomplete="off">
            </label>

            <label class="pm-filter-field">
                <span class="pm-filter-label">Product Category</span>
                <select name="category" class="pm-filter-input">
                    <option value="">All categories</option>
                    @foreach ($categoryOptions as $category)
                        <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </label>

            <label class="pm-filter-field">
                <span class="pm-filter-label">Product Type</span>
                <select name="type" class="pm-filter-input">
                    <option value="all" @selected($selectedType === 'all')>All types</option>
                    <option value="simple" @selected($selectedType === 'simple')>Simple</option>
                    <option value="variable" @selected($selectedType === 'variable')>Variable</option>
                </select>
            </label>

            <label class="pm-filter-field">
                <span class="pm-filter-label">Readiness</span>
                <select name="health" class="pm-filter-input">
                    <option value="all" @selected($selectedHealth === 'all')>All readiness</option>
                    <option value="ok" @selected($selectedHealth === 'ok')>Ready</option>
                    <option value="needs" @selected($selectedHealth === 'needs')>Needs work</option>
                </select>
            </label>

            <label class="pm-filter-field pm-filter-field--compact">
                <span class="pm-filter-label">Page size</span>
                <select name="per_page" class="pm-filter-input">
                    @foreach ([10, 20, 50] as $size)
                        <option value="{{ $size }}" @selected($selectedPerPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>

            <div class="pm-filter-actions">
                <button type="submit" class="pm-filter-apply">Apply</button>
                <a href="{{ route('product-map.index') }}" class="pm-filter-clear">Clear</a>
            </div>
        </div>
    </form>
</div>
