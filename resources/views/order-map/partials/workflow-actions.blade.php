@php
    use App\Enums\SfmOrderStatus;

    $status = $status ?? ($order->sfm_status ?? SfmOrderStatus::New);
    $transitions = $availableTransitions ?? [];
@endphp

<div class="order-map-workflow-actions">
    @if ($status === SfmOrderStatus::New)
        @if (in_array('accepted', $transitions, true))
            <form method="POST" action="{{ route('order-map.accept', $order) }}" class="order-map-workflow-form">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm w-full">Accept</button>
            </form>
        @endif
        @if (in_array('rejected', $transitions, true))
            <form method="POST" action="{{ route('order-map.reject', $order) }}" class="order-map-workflow-form"
                  onsubmit="return confirm('Reject this order and restore stock?');">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm w-full">Reject</button>
            </form>
        @endif
    @elseif ($status === SfmOrderStatus::Accepted)
        @if (in_array('packed', $transitions, true))
            <form method="POST" action="{{ route('order-map.pack', $order) }}" class="order-map-workflow-form">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm w-full">Pack</button>
            </form>
        @endif
        @if (in_array('rejected', $transitions, true))
            <form method="POST" action="{{ route('order-map.reject', $order) }}" class="order-map-workflow-form"
                  onsubmit="return confirm('Reject this order and restore stock?');">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm w-full">Reject</button>
            </form>
        @endif
    @elseif ($status === SfmOrderStatus::Packed)
        @if (in_array('dispatched', $transitions, true))
            <div class="order-map-dispatch-block">
                <button type="button"
                        class="btn btn-primary btn-sm w-full"
                        data-dispatch-reveal
                        data-dispatch-target="dispatch-form-{{ $order->id }}">
                    Dispatch
                </button>
                <form method="POST"
                      action="{{ route('order-map.dispatch', $order) }}"
                      id="dispatch-form-{{ $order->id }}"
                      class="order-map-dispatch-form space-y-2"
                      data-dispatch-form
                      hidden
                      onsubmit="return confirm('Dispatch this order?');">
                    @csrf
                    <label class="block text-xs text-slate-500">
                        Consignment ID <span class="text-red-600">*</span>
                        <input type="text" name="consignment_id" class="form-input mt-1" placeholder="Consignment ID" required>
                    </label>
                    <label class="block text-xs text-slate-500">
                        Courier / delivery partner
                        <input type="text" name="courier" class="form-input mt-1" placeholder="Optional">
                    </label>
                    <p class="text-xs text-slate-500">Consignment ID is required. Order must be packed.</p>
                    <button type="submit" class="btn btn-primary btn-sm w-full">Confirm Dispatch</button>
                </form>
            </div>
        @endif
    @elseif ($status === SfmOrderStatus::Dispatched)
        <a href="{{ route('order-map.print-invoice', $order) }}"
           class="btn btn-primary btn-sm w-full"
           target="_blank"
           rel="noopener">Print Invoice</a>
        <p class="text-sm text-slate-600">Order dispatched.</p>
    @elseif ($status === SfmOrderStatus::ReturnQueue)
        @if (in_array('return_received', $transitions, true))
            <form method="POST" action="{{ route('order-map.return-received', $order) }}" class="order-map-workflow-form"
                  onsubmit="return confirm('Mark this return as received?');">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm w-full">Mark Return Received</button>
            </form>
        @endif
    @elseif (in_array($status, [SfmOrderStatus::ReturnReceived, SfmOrderStatus::Completed, SfmOrderStatus::Rejected], true))
        <p class="text-sm text-slate-600">No workflow actions for this status.</p>
    @endif
</div>
