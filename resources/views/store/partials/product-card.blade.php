@php
    $variant = $product->defaultVariant;
    $inStock = $variant && ($variant->qty > 0 || $variant->stock_status === 'instock');
@endphp
<article class="card-product">
    <a href="{{ route('product.show', $product->slug) }}">
        <div class="img">
            <span class="stock-badge {{ $inStock ? 'in' : 'out' }}">{{ $inStock ? 'In stock' : 'Call' }}</span>
            @if($product->primaryImage?->path)
                <img src="{{ asset('storage/'.$product->primaryImage->path) }}" alt="{{ $product->name }}" loading="lazy">
            @else
                {{ $product->brand?->name ?? 'MTC' }}
            @endif
        </div>
    </a>
    <div class="body">
        @if($product->brand)
            <div class="brand">{{ $product->brand->name }}</div>
        @endif
        <div class="sku">{{ $product->part_number ?? $product->sku }}</div>
        <a class="title" href="{{ route('product.show', $product->slug) }}">{{ $product->name }}</a>
        <div class="price">${{ number_format((float) ($product->price ?? 0), 2) }}</div>
        @if($variant)
            <form class="card-actions" method="post" action="{{ route('cart.add') }}">
                @csrf
                <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                <input type="hidden" name="qty" value="1">
                <button class="btn btn-block" type="submit" @if(!$inStock) disabled @endif>{{ $inStock ? 'Add to cart' : 'Out of stock' }}</button>
            </form>
        @endif
    </div>
</article>
