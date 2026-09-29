@extends('layouts.store')

@section('title', 'About Us | MTC Truck Parts')
@section('meta_description', 'Macomb Truck Center supplies heavy duty truck and trailer parts across the United States.')

@section('content')
<section class="page-hero">
    <div class="page-hero-inner">
        <h1>About MTC Truck Parts</h1>
        <p style="margin:0; opacity:.85; max-width:40ch;">Heavy duty parts. U.S. warehouses. People who know trucks.</p>
    </div>
</section>
<div class="wrap">
    <div class="prose">
        <p>Macomb Truck Center (MTC Truck Parts) is a trusted source for heavy duty truck and trailer parts throughout the United States. We stock OEM and aftermarket components for owner-operators, repair shops, dealerships, and commercial fleets.</p>
        <p>Whether you need air springs, brake parts, suspension, steering, engine components, filters, or drivetrain products, our catalog is built for fast part-number and cross-reference lookup.</p>
        <h2>Warehouses</h2>
        <ul>
            <li>Michigan — primary fulfillment</li>
            <li>Illinois — Midwest shipping</li>
            <li>Toledo, OH — opening soon at 1336 Matzinger Rd</li>
        </ul>
        <p><a class="btn" href="{{ route('page.contact') }}">Contact our team</a></p>
    </div>
</div>
@endsection
