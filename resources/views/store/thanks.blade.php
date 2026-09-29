@extends('layouts.store')

@section('title', 'Thank you | MTC Truck Parts')

@section('content')
<div class="wrap" style="max-width:720px;">
    <h1 class="section-title">Thank you</h1>
    <div class="panel">
        <p style="margin-top:0;">Your order <strong>{{ $order->number }}</strong> was created.</p>
        <p>Total: <strong>${{ number_format((float) $order->grand_total, 2) }}</strong></p>
        <p class="summary-note">Payment status: {{ $order->payment_status }}. Our team will follow up if anything is needed. Questions? Call <a href="tel:+15868844156" style="color:var(--mtc-red); font-weight:700;">(586) 884-4156</a>.</p>
        <a class="btn" href="{{ route('shop') }}">Continue shopping</a>
    </div>
</div>
@endsection
