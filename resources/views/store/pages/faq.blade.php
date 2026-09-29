@extends('layouts.store')

@section('title', 'FAQs | MTC Truck Parts')
@section('meta_description', 'Frequently asked questions about ordering heavy duty truck parts from MTC.')

@section('content')
<section class="page-hero">
    <div class="page-hero-inner">
        <h1>FAQs</h1>
        <p style="margin:0; opacity:.85;">Quick answers for shops and fleets.</p>
    </div>
</section>
<div class="wrap">
    <div class="prose">
        <h2>How do I find a part?</h2>
        <p>Use the search bar with an OEM number, SKU, or competitor cross reference. You can also browse by category or brand.</p>

        <h2>Do you ship nationwide?</h2>
        <p>Yes. We ship UPS nationwide from Michigan and Illinois warehouses.</p>

        <h2>What if I am not sure about fitment?</h2>
        <p>Call <a href="tel:+15868844156" style="color:var(--mtc-red); font-weight:700;">+1 (586) 884-4156</a> with your part number or application details. Our U.S. team can help confirm the correct item.</p>

        <h2>How do returns and cores work?</h2>
        <p>See our <a href="{{ route('page.returns') }}" style="color:var(--mtc-red); font-weight:700;">Returns &amp; Core</a> page, then contact us before shipping anything back.</p>
    </div>
</div>
@endsection
