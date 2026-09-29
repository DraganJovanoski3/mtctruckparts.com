@extends('layouts.store')

@section('title', isset($category) ? $category->name.' | MTC Truck Parts' : 'Shop Heavy Duty Truck Parts | MTC')
@section('meta_description', isset($category) ? 'Shop '.$category->name.' heavy duty truck parts at MTC.' : 'Browse heavy duty truck and trailer parts. Filter by brand, category, and in-stock.')

@section('content')
<div class="wrap" id="filters">
    @include('store.partials.breadcrumbs', [
        'crumbs' => isset($category)
            ? [['label' => 'Shop', 'url' => route('shop')], ['label' => $category->name]]
            : [['label' => 'Shop']],
    ])

    <div class="plp-layout">
        <aside>
            <button type="button" class="btn btn-outline filter-toggle" id="filterToggle" aria-expanded="false">Filters</button>
            <form class="filters" id="filterPanel" method="get" action="{{ route('shop') }}">
                <h2 style="margin:0 0 .75rem; font-size:1rem;">Filters</h2>

                <div class="field">
                    <label for="filter-q">Search</label>
                    <input id="filter-q" type="search" name="q" value="{{ $search }}" placeholder="Part # or cross ref">
                </div>

                <div class="field">
                    <label for="filter-brand">Brand</label>
                    <select id="filter-brand" name="brand">
                        <option value="">All brands</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand->slug }}" @selected(request('brand') === $brand->slug)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="filter-category">Category</label>
                    <select id="filter-category" name="category">
                        <option value="">All categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug || (isset($category) && $category->slug === $cat->slug))>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="filter-check">
                        <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))>
                        In stock only
                    </label>
                </div>

                <div class="field">
                    <label for="filter-sort">Sort</label>
                    <select id="filter-sort" name="sort">
                        <option value="newest" @selected($sort === 'newest')>Newest</option>
                        <option value="price_asc" @selected($sort === 'price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected($sort === 'price_desc')>Price: High to Low</option>
                        <option value="name" @selected($sort === 'name')>Name</option>
                    </select>
                </div>

                <button class="btn btn-block" type="submit">Apply filters</button>
            </form>
        </aside>

        <div>
            <div class="plp-head">
                <div>
                    <h1 class="section-title" style="margin:0;">{{ isset($category) ? $category->name : 'Shop Parts' }}</h1>
                    <p class="plp-meta">{{ $products->total() }} products</p>
                </div>
            </div>

            @php
                $chips = collect([
                    request('q') ? ['label' => 'Search: '.request('q'), 'remove' => 'q'] : null,
                    request('brand') ? ['label' => 'Brand: '.request('brand'), 'remove' => 'brand'] : null,
                    request('category') && !isset($category) ? ['label' => 'Category: '.request('category'), 'remove' => 'category'] : null,
                    request()->boolean('in_stock') ? ['label' => 'In stock', 'remove' => 'in_stock'] : null,
                ])->filter();
            @endphp
            @if($chips->isNotEmpty())
                <div class="filter-chips">
                    @foreach($chips as $chip)
                        @php $params = request()->except($chip['remove']); @endphp
                        <span class="chip">{{ $chip['label'] }} <a href="{{ route('shop', $params) }}" aria-label="Remove filter">×</a></span>
                    @endforeach
                    <a class="chip" href="{{ route('shop') }}">Clear all</a>
                </div>
            @endif

            @if($products->isEmpty())
                <div class="empty-state">
                    <h2 style="margin-top:0;">No products found</h2>
                    <p>Try an OEM number, competitor cross reference, or clear filters.</p>
                    <a class="btn" href="{{ route('shop') }}">Browse all parts</a>
                </div>
            @else
                <div class="grid-products">
                    @foreach($products as $product)
                        @include('store.partials.product-card', ['product' => $product])
                    @endforeach
                </div>
                <div class="pager-wrap">{{ $products->links('vendor.pagination.store') }}</div>
            @endif
        </div>
    </div>
</div>

<script>
document.getElementById('filterToggle')?.addEventListener('click', function () {
    const panel = document.getElementById('filterPanel');
    const open = panel.classList.toggle('is-open');
    this.setAttribute('aria-expanded', open ? 'true' : 'false');
    this.textContent = open ? 'Hide filters' : 'Filters';
});
</script>
@endsection
