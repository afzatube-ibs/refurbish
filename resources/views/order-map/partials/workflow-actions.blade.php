@if (in_array('accepted', $availableTransitions))
    <form method="POST" action="{{ route('order-map.accept', $order) }}" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm w-full">Accept</button>
    </form>
@endif

@if (in_array('packed', $availableTransitions))
    <form method="POST" action="{{ route('order-map.pack', $order) }}" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm w-full">Mark Packed</button>
    </form>
@endif

@if (in_array('dispatched', $availableTransitions))
    <form method="POST" action="{{ route('order-map.dispatch', $order) }}" class="space-y-2 mb-2">
        @csrf
        <input type="text" name="courier" class="form-input" placeholder="Courier" required>
        <input type="text" name="consignment_id" class="form-input" placeholder="Consignment ID" required>
        <button type="submit" class="btn btn-primary btn-sm w-full">Dispatch</button>
    </form>
@endif

@if (in_array('rejected', $availableTransitions))
    <form method="POST" action="{{ route('order-map.reject', $order) }}" class="mb-2" onsubmit="return confirm('Reject this order and restore stock?');">
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm w-full text-red-700">Reject</button>
    </form>
@endif

@if (in_array('return_queue', $availableTransitions))
    <form method="POST" action="{{ route('order-map.return-queue', $order) }}" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm w-full">Return Queue</button>
    </form>
@endif

@if (in_array('return_received', $availableTransitions))
    <form method="POST" action="{{ route('order-map.return-received', $order) }}" class="mb-2">
        @csrf
        <button type="submit" class="btn btn-secondary btn-sm w-full">Return Received</button>
    </form>
@endif
