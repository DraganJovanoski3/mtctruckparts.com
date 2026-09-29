@extends('layouts.store')

@section('title', 'Cart | MTC Truck Parts')

@section('content')
<div class="wrap">
    @include('store.partials.breadcrumbs', ['crumbs' => [['label' => 'Cart']]])
    <h1 class="section-title">Your cart</h1>

    @if($items->isEmpty())
        <div class="empty-state">
            <p style="margin-top:0;">Your cart is empty.</p>
            <a class="btn" href="{{ route('shop') }}">Continue shopping</a>
        </div>
    @else
        <div class="cart-layout">
            <div class="cart-lines">
                @foreach($items as $item)
                    @php $img = $item['product']->primaryImage?->path; @endphp
                    <div class="cart-line">
                        <div class="cart-thumb">
                            @if($img)
                                <img src="{{ asset('storage/'.$img) }}" alt="">
                            @else
                                MTC
                            @endif
                        </div>
                        <div>
                            <strong><a href="{{ route('product.show', $item['product']->slug) }}">{{ $item['product']->name }}</a></strong>
                            <div class="sku">SKU {{ $item['variant']->sku }}</div>
                            <div style="color:var(--mtc-muted); font-size:.9rem;">${{ number_format((float) $item['variant']->price, 2) }} each</div>
                            <form method="post" action="{{ route('cart.update') }}" style="display:flex; gap:.4rem; margin-top:.55rem; align-items:center;">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $item['variant']->id }}">
                                <input type="number" name="qty" value="{{ $item['qty'] }}" min="0" max="99" style="width:4rem; padding:.4rem; border:1px solid var(--mtc-line);">
                                <button class="btn-outline" type="submit" style="padding:.4rem .7rem; border:1px solid var(--mtc-line); background:#fff; cursor:pointer; font-weight:600;">Update</button>
                            </form>
                        </div>
                        <div class="line-total" style="font-weight:700;">${{ number_format($item['line_total'], 2) }}</div>
                    </div>
                @endforeach
            </div>

            <aside class="summary-card">
                <h2 style="margin:0 0 1rem; font-size:1.1rem;">Order summary</h2>
                <div class="summary-row"><span>Subtotal</span><strong>${{ number_format($subtotal, 2) }}</strong></div>

                <div class="coupon-box">
                    @if($coupon)
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
                        <div class="summary-row"><span>Discount</span><strong style="color:var(--mtc-ok);">−${{ number_format($discount, 2) }}</strong></div>
                    @else
                        <form method="post" action="{{ route('cart.coupon') }}" class="coupon-form">
                            @csrf
                            <label for="coupon_code">Coupon code</label>
                            <div class="coupon-form-row">
                                <input id="coupon_code" name="coupon_code" type="text" placeholder="Enter code" value="{{ old('coupon_code') }}" required>
                                <button class="btn btn-outline" type="submit">Apply</button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="summary-row"><span>Shipping</span><span>Calculated next</span></div>
                <div class="summary-row summary-total"><span>Total</span><span>${{ number_format($total, 2) }}</span></div>
                <p class="summary-note">Questions on fitment or core returns? Call <a href="tel:+15868844156" style="color:var(--mtc-red); font-weight:700;">(586) 884-4156</a>.</p>
                <a class="btn btn-block" href="{{ route('checkout') }}">Proceed to checkout</a>
                <p style="margin:.85rem 0 0; text-align:center;"><a href="{{ route('shop') }}" style="font-weight:600;">Continue shopping</a></p>
            </aside>
        </div>
    @endif
</div>
@endsection
