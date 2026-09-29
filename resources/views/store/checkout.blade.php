@extends('layouts.store')

@section('title', 'Checkout | MTC Truck Parts')

@section('content')
<div class="wrap">
    @include('store.partials.breadcrumbs', ['crumbs' => [
        ['label' => 'Cart', 'url' => route('cart')],
        ['label' => 'Checkout'],
    ]])
    <h1 class="section-title">Checkout</h1>

    <div class="checkout-layout">
        <form class="checkout-form" id="checkoutForm" method="post" action="{{ route('checkout.place') }}">
            @csrf
            <div class="form-grid">
                <h2 class="checkout-section-title">Contact</h2>
                <div class="form-row-2">
                    <input name="first_name" id="first_name" placeholder="First name" required value="{{ old('first_name') }}">
                    <input name="last_name" id="last_name" placeholder="Last name" required value="{{ old('last_name') }}">
                </div>
                <input type="email" name="email" id="email" placeholder="Email" required value="{{ old('email') }}">
                <input name="phone" id="phone" placeholder="Phone" value="{{ old('phone') }}">

                <h2 class="checkout-section-title">Shipping address</h2>
                <input name="shipping_address" id="shipping_address" placeholder="Street address" required value="{{ old('shipping_address', old('address')) }}">
                <div class="form-row-3">
                    <input name="shipping_city" id="shipping_city" placeholder="City" required value="{{ old('shipping_city', old('city')) }}">
                    <input name="shipping_state" id="shipping_state" placeholder="State" required value="{{ old('shipping_state', old('state')) }}">
                    <input name="shipping_zip" id="shipping_zip" placeholder="ZIP" required value="{{ old('shipping_zip', old('zip')) }}">
                </div>

                <div class="same-address-row">
                    <label class="filter-check same-address-check">
                        <input type="checkbox" name="same_as_shipping" id="sameAsShipping" value="1"
                            @checked(old('same_as_shipping', '1') === '1')>
                        Billing address same as shipping
                    </label>
                    <button type="button" class="btn btn-outline" id="copyShippingBtn">Use shipping details</button>
                </div>

                <div id="billingFields" class="billing-fields" @if(old('same_as_shipping', '1') === '1') hidden @endif>
                    <h2 class="checkout-section-title">Billing address</h2>
                    <input name="billing_first_name" id="billing_first_name" placeholder="First name" value="{{ old('billing_first_name') }}">
                    <input name="billing_last_name" id="billing_last_name" placeholder="Last name" value="{{ old('billing_last_name') }}">
                    <input name="billing_address" id="billing_address" placeholder="Street address" value="{{ old('billing_address') }}">
                    <div class="form-row-3">
                        <input name="billing_city" id="billing_city" placeholder="City" value="{{ old('billing_city') }}">
                        <input name="billing_state" id="billing_state" placeholder="State" value="{{ old('billing_state') }}">
                        <input name="billing_zip" id="billing_zip" placeholder="ZIP" value="{{ old('billing_zip') }}">
                    </div>
                </div>

                <p class="summary-note" style="margin:0;">Card payment via Authorize.net will be connected next. Placing this order creates your order for our sales team.</p>
                <button class="btn" type="submit">Place order</button>
            </div>
        </form>

        <aside class="summary-card">
            <h2 style="margin:0 0 1rem; font-size:1.1rem;">Order summary</h2>
            @foreach($items as $item)
                <div class="summary-row" style="font-size:.92rem;">
                    <span>{{ $item['qty'] }} × {{ $item['variant']->sku }}</span>
                    <span>${{ number_format($item['line_total'], 2) }}</span>
                </div>
            @endforeach
            <div class="summary-row"><span>Subtotal</span><span>${{ number_format($subtotal, 2) }}</span></div>

            <div class="coupon-box">
                @if($coupon ?? null)
                    <div class="coupon-applied">
                        <div>
                            <strong>{{ $coupon->code }}</strong>
                            <span>{{ $coupon->label() }}</span>
                        </div>
                        <form method="post" action="{{ route('cart.coupon.remove') }}">
                            @csrf
                            <button type="submit" class="coupon-remove">Remove</button>
                        </form>
                    </div>
                    @if(($discount ?? 0) > 0)
                        <div class="summary-row" style="margin-top:.65rem;">
                            <span>Discount</span>
                            <strong style="color:var(--mtc-ok);">−${{ number_format($discount, 2) }}</strong>
                        </div>
                    @endif
                @else
                    <form method="post" action="{{ route('cart.coupon') }}" class="coupon-form">
                        @csrf
                        <label for="checkout_coupon_code">Have a coupon?</label>
                        <div class="coupon-form-row">
                            <input id="checkout_coupon_code" name="coupon_code" type="text" placeholder="Enter code" value="{{ old('coupon_code') }}" autocomplete="off" required>
                            <button class="btn btn-outline" type="submit">Apply</button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="summary-row summary-total"><span>Total</span><span>${{ number_format($total ?? $subtotal, 2) }}</span></div>
            <p class="summary-note">Secure order handling · U.S.-based support · <a href="{{ route('page.returns') }}" style="color:var(--mtc-red);">Returns &amp; core</a></p>
        </aside>
    </div>
</div>

<script>
(function () {
    var same = document.getElementById('sameAsShipping');
    var billing = document.getElementById('billingFields');
    var copyBtn = document.getElementById('copyShippingBtn');
    var form = document.getElementById('checkoutForm');

    var map = [
        ['first_name', 'billing_first_name'],
        ['last_name', 'billing_last_name'],
        ['shipping_address', 'billing_address'],
        ['shipping_city', 'billing_city'],
        ['shipping_state', 'billing_state'],
        ['shipping_zip', 'billing_zip']
    ];

    function copyShippingToBilling() {
        map.forEach(function (pair) {
            var from = document.getElementById(pair[0]);
            var to = document.getElementById(pair[1]);
            if (from && to) to.value = from.value;
        });
    }

    function setBillingRequired(on) {
        ['billing_first_name', 'billing_last_name', 'billing_address', 'billing_city', 'billing_state', 'billing_zip']
            .forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.required = on;
            });
    }

    function syncBillingVisibility() {
        var useSame = same && same.checked;
        if (billing) billing.hidden = !!useSame;
        if (copyBtn) copyBtn.hidden = !!useSame;
        setBillingRequired(!useSame);
        if (useSame) copyShippingToBilling();
    }

    if (same) same.addEventListener('change', syncBillingVisibility);
    if (copyBtn) copyBtn.addEventListener('click', copyShippingToBilling);
    if (form) {
        form.addEventListener('submit', function () {
            if (same && same.checked) copyShippingToBilling();
        });
    }
    syncBillingVisibility();
})();
</script>
@endsection
