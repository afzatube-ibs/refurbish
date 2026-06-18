<form method="GET" action="{{ route('reports.dispatch') }}" class="df-filter-row">
    @include('reports.partials.scope-filters')
    <div class="df-filter-group">
        <label for="from" class="df-filter-label">From</label>
        <input type="date" name="from" id="from" value="{{ request('from', $from ?? '') }}" class="df-date">
    </div>
    <div class="df-filter-group">
        <label for="to" class="df-filter-label">To</label>
        <input type="date" name="to" id="to" value="{{ request('to', $to ?? '') }}" class="df-date">
    </div>
    <div class="df-filter-group">
        <label for="courier" class="df-filter-label">Courier</label>
        <input type="text" name="courier" id="courier" value="{{ request('courier', $courier ?? '') }}"
               placeholder="Courier name" class="df-input">
    </div>
    <div class="df-filter-group df-filter-group--wide">
        <label for="search" class="df-filter-label">Search</label>
        <input type="text" name="search" id="search" value="{{ request('search', $search ?? '') }}"
               placeholder="Order no or phone" class="df-input">
    </div>
    <div class="df-filter-actions">
        <button type="submit" class="df-btn df-btn--secondary">Filter</button>
    </div>
</form>
