@extends('layouts.store')

@section('title', ($product->meta_title ?: $product->name).' | MTC Truck Parts')
@section('meta_description', $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($product->description ?? $product->name), 155))

@push('head')
@php
    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'sku' => $product->sku,
        'mpn' => $product->part_number,
        'brand' => ['@type' => 'Brand', 'name' => $product->brand?->name ?? 'MTC'],
        'description' => strip_tags($product->description ?? ''),
        'image' => $product->primaryImage?->path ? asset('storage/'.$product->primaryImage->path) : null,
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'USD',
            'price' => (float) ($product->price ?? 0),
            'availability' => ($product->defaultVariant?->qty > 0 || $product->defaultVariant?->stock_status === 'instock')
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
@php
    $variant = $product->defaultVariant;
    $inStock = $variant && ($variant->qty > 0 || $variant->stock_status === 'instock');
    $crumbs = [
        ['label' => 'Shop', 'url' => route('shop')],
    ];
    if ($product->categories->first()) {
        $crumbs[] = ['label' => $product->categories->first()->name, 'url' => route('category.show', $product->categories->first()->slug)];
    }
    $crumbs[] = ['label' => $product->name];
@endphp

<div class="wrap">
    @include('store.partials.breadcrumbs', ['crumbs' => $crumbs])

    <div class="pdp-grid">
        <div class="pdp-gallery">
            @if($product->primaryImage?->path)
                <img src="{{ asset('storage/'.$product->primaryImage->path) }}" alt="{{ $product->name }}">
            @elseif($product->images->first())
                <img src="{{ asset('storage/'.$product->images->first()->path) }}" alt="{{ $product->name }}">
            @else
                {{ $product->brand?->name ?? 'Product image' }}
            @endif
        </div>

        <div class="pdp-buy">
            @if($product->brand)
                <p class="pdp-brand">{{ $product->brand->name }}</p>
            @endif
            <h1 class="pdp-title">{{ $product->name }}</h1>

            <div class="pdp-meta">
                <div><strong>Part #:</strong> {{ $product->part_number ?? '—' }}</div>
                <div><strong>SKU:</strong> {{ $product->sku ?? '—' }}</div>
            </div>

            <p class="pdp-price">${{ number_format((float) ($product->price ?? 0), 2) }}</p>
            <p class="pdp-stock {{ $inStock ? 'in' : 'out' }}">
                @if($inStock)
                    In stock{{ $variant->qty > 0 ? ' ('.$variant->qty.')' : '' }} — ready to ship
                @else
                    Out of stock — call for availability
                @endif
            </p>

            @if($variant)
                <form method="post" action="{{ route('cart.add') }}">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <div class="qty-row">
                        <input type="number" name="qty" value="1" min="1" max="99" aria-label="Quantity" @if(!$inStock) disabled @endif>
                        <button class="btn" type="submit" style="flex:1;" @if(!$inStock) disabled @endif>
                            {{ $inStock ? 'Add to cart' : 'Unavailable' }}
                        </button>
                    </div>
                </form>
            @endif

            <p class="pdp-help">Need the right cross? <a href="tel:+15868844156">Call +1 (586) 884-4156</a></p>
            <p class="summary-note" style="margin-bottom:0;">UPS shipping from Michigan &amp; Illinois warehouses. Returns &amp; core policy available at checkout support.</p>
        </div>
    </div>

    <div class="panel">
        <h2>Cross references</h2>
        @forelse($product->crossReferences as $ref)
            <div class="xref-row">
                <span>{{ $ref->ref_number }}</span>
                <span class="xref-type">{{ $ref->ref_type }}</span>
            </div>
        @empty
            <p style="margin:0; color:var(--mtc-muted);">No cross references listed for this part.</p>
        @endforelse
    </div>

    @if($product->description || $product->short_description)
        <div class="panel">
            <h2>Description</h2>
            @if($product->short_description)
                <p style="font-weight:600;">{{ $product->short_description }}</p>
            @endif
            <div class="prose">{!! $product->description !!}</div>
        </div>
    @endif

    @if($variant && ($variant->weight || $variant->length || $variant->width || $variant->height))
        <div class="panel">
            <h2>Specifications</h2>
            <div class="pdp-meta">
                @if($variant->weight)<div><strong>Weight:</strong> {{ $variant->weight }}</div>@endif
                @if($variant->length)<div><strong>Length:</strong> {{ $variant->length }}</div>@endif
                @if($variant->width)<div><strong>Width:</strong> {{ $variant->width }}</div>@endif
                @if($variant->height)<div><strong>Height:</strong> {{ $variant->height }}</div>@endif
            </div>
        </div>
    @endif

    @if($related->isNotEmpty())
        <section style="margin-top:2rem;">
            <h2 class="section-title">Related parts</h2>
            <div class="grid-products">
                @foreach($related as $item)
                    @include('store.partials.product-card', ['product' => $item])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
