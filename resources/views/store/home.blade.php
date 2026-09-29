@extends('layouts.store')

@section('title', 'Heavy Duty Truck & Trailer Parts | MTC Truck Parts')
@section('meta_description', 'Shop 50,000+ heavy duty truck and trailer parts. Search by part number or cross reference. OEM and aftermarket. Fast UPS shipping from U.S. warehouses.')

@section('content')
<section class="hero">
    <div class="hero-inner">
        <p class="hero-kicker">50,000+ parts in stock</p>
        <h1>Heavy Duty Truck &amp; Trailer Parts</h1>
        <p>Quick lookup by part number or cross reference. OEM and aftermarket brands. U.S. support by phone.</p>
        <div class="hero-ctas">
            <a class="btn" href="{{ route('shop') }}">Shop Now</a>
            <a class="btn btn-ghost" href="{{ route('shop') }}#filters">Search by part #</a>
        </div>
    </div>
</section>

<section class="wrap" style="padding-bottom:1rem;">
    <div class="trust-strip">
        <div class="trust-item"><strong>Part # search</strong><span>SKU &amp; cross reference</span></div>
        <div class="trust-item"><strong>50,000+ SKUs</strong><span>OEM &amp; aftermarket</span></div>
        <div class="trust-item"><strong>UPS nationwide</strong><span>From MI &amp; IL warehouses</span></div>
        <div class="trust-item"><strong>U.S. support</strong><span>(586) 884-4156</span></div>
    </div>
</section>

<section class="wrap">
    <h2 class="section-title">Shop by category</h2>
    <div class="cat-grid">
        @foreach($categories as $category)
            <a class="cat-tile" href="{{ route('category.show', $category->slug) }}">
                <div>
                    <small>{{ $category->children->count() }} subcategories</small>
                    {{ $category->menuLabel() }}
                </div>
            </a>
        @endforeach
    </div>
</section>

<section class="wrap" style="padding-top:0;">
    <div class="plp-head">
        <h2 class="section-title" style="margin:0;">Featured parts</h2>
        <a href="{{ route('shop') }}" style="font-weight:700; color:var(--mtc-red);">View all →</a>
    </div>
    <div class="grid-products">
        @forelse($featured as $product)
            @include('store.partials.product-card', ['product' => $product])
        @empty
            <div class="empty-state">Featured products will appear here once marked in admin.</div>
        @endforelse
    </div>
</section>

<section class="wrap" style="padding-top:0;">
    <h2 class="section-title">Latest arrivals</h2>
    <div class="grid-products">
        @foreach($latest as $product)
            @include('store.partials.product-card', ['product' => $product])
        @endforeach
    </div>
</section>

<section class="wrap" style="padding-top:0;">
    <h2 class="section-title">Shop by brand</h2>
    <div class="brand-strip">
        @foreach($brands as $brand)
            <a class="brand-pill" href="{{ route('shop', ['brand' => $brand->slug]) }}">{{ $brand->name }}</a>
        @endforeach
    </div>
</section>

<section class="wrap" style="padding-top:0;">
    <h2 class="section-title">Why fleets buy from MTC</h2>
    <div class="why-grid">
        <div class="why-copy">
            <p style="margin-top:0;">Macomb Truck Center supplies heavy duty truck and trailer parts for owner-operators, repair shops, dealerships, and commercial fleets. Find the right part fast by OEM number, competitor cross reference, or SKU — then call our U.S. team if you need a hand.</p>
            <p><a class="btn" href="tel:+15868844156">Call sales +1 (586) 884-4156</a></p>
        </div>
        <div class="why-warehouses">
            <h3 style="margin:0 0 .75rem; font-size:1rem;">Warehouses</h3>
            <ul>
                <li>Michigan — primary fulfillment</li>
                <li>Illinois — Midwest shipping</li>
                <li class="soon">Toledo, OH — coming soon (1336 Matzinger Rd)</li>
            </ul>
        </div>
    </div>
</section>
@endsection
