@extends('layouts.store')

@section('title', 'Contact | MTC Truck Parts')
@section('meta_description', 'Contact MTC Truck Parts sales and support at +1 (586) 884-4156.')

@section('content')
<section class="page-hero">
    <div class="page-hero-inner">
        <h1>Contact</h1>
        <p style="margin:0; opacity:.85;">U.S.-based support for part lookup and orders.</p>
    </div>
</section>
<div class="wrap">
    <div class="why-grid">
        <div class="panel" style="margin:0;">
            <h2>Talk to sales</h2>
            <p><strong>Phone:</strong> <a href="tel:+15868844156" style="color:var(--mtc-red);">+1 (586) 884-4156</a></p>
            <p><strong>Email:</strong> <a href="mailto:info@mtctruckparts.com" style="color:var(--mtc-red);">info@mtctruckparts.com</a></p>
            <p class="summary-note">Have your part number, VIN if available, or competitor cross reference ready — we will help identify the correct SKU.</p>
        </div>
        <div class="panel" style="margin:0;">
            <h2>Send a message</h2>
            <form class="form-grid" style="padding:0;" action="mailto:info@mtctruckparts.com" method="get" enctype="text/plain">
                <input type="text" name="subject" placeholder="Subject" required>
                <textarea name="body" rows="6" placeholder="Part numbers, order questions, or warehouse needs" required style="width:100%; padding:.75rem; border:1px solid var(--mtc-line);"></textarea>
                <button class="btn" type="submit">Open email</button>
            </form>
        </div>
    </div>
</div>
@endsection
