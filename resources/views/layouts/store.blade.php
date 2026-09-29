<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Heavy Duty Truck Parts | MTC Truck Parts')</title>
    <meta name="description" content="@yield('meta_description', 'Shop 50,000+ heavy duty truck and trailer parts. OEM and aftermarket. Fast U.S. shipping from Michigan and Illinois.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('app.noindex'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="stylesheet" href="{{ asset('css/store.css') }}?v=10">
    @stack('head')
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <span class="topbar-ship">UPS nationwide · MI &amp; IL</span>
            <span class="topbar-promo">10% OFF first order $100–$300</span>
            <a class="topbar-call" href="tel:+15868844156">Call (586) 884-4156</a>
        </div>
    </div>

    <header class="site-header">
        <div class="header-inner">
            <button type="button" class="menu-toggle" id="menuToggle" aria-controls="mobileNav" aria-expanded="false" aria-label="Open menu">
                <span></span><span></span><span></span>
            </button>

            <a href="{{ route('home') }}" class="logo" aria-label="MTC Truck Parts home">
                MTC <span>TRUCK PARTS</span>
            </a>

            <form class="search-form search-desktop" action="{{ route('shop') }}" method="get" role="search">
                <label class="sr-only" for="q">Search part number</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search part #, SKU, or cross reference" autocomplete="off">
                <button type="submit">Search</button>
            </form>

            <div class="header-actions">
                <a class="header-phone" href="tel:+15868844156">
                    <span class="header-phone-label">Sales</span>
                    <span class="header-phone-num">(586) 884-4156</span>
                </a>
                <div class="cart-wrap" id="cartWrap">
                    <button type="button" class="header-cart" id="cartToggle" aria-expanded="false" aria-controls="cartDropdown" aria-haspopup="true">
                        <span class="cart-text">Cart</span>
                        <span class="cart-badge">{{ $cartCount }}</span>
                    </button>
                    <div class="cart-dropdown" id="cartDropdown" hidden>
                        <div class="cart-dropdown-head">
                            <strong>Your cart</strong>
                            <span>{{ $cartCount }} {{ $cartCount === 1 ? 'item' : 'items' }}</span>
                        </div>
                        @if(($cartPreviewItems ?? collect())->isEmpty())
                            <div class="cart-dropdown-empty">
                                <p>Your cart is empty.</p>
                                <a class="btn btn-block" href="{{ route('shop') }}">Shop parts</a>
                            </div>
                        @else
                            <ul class="cart-dropdown-list">
                                @foreach($cartPreviewItems as $item)
                                    @php $img = $item['product']->primaryImage?->path; @endphp
                                    <li class="cart-dropdown-item">
                                        <a class="cart-dropdown-thumb" href="{{ route('product.show', $item['product']->slug) }}">
                                            @if($img)
                                                <img src="{{ asset('storage/'.$img) }}" alt="">
                                            @else
                                                <span>MTC</span>
                                            @endif
                                        </a>
                                        <div class="cart-dropdown-meta">
                                            <a href="{{ route('product.show', $item['product']->slug) }}">{{ $item['product']->name }}</a>
                                            <span>SKU {{ $item['variant']->sku }} · Qty {{ $item['qty'] }}</span>
                                            <strong>${{ number_format($item['line_total'], 2) }}</strong>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="cart-dropdown-foot">
                                <div class="cart-dropdown-subtotal">
                                    <span>Subtotal</span>
                                    <strong>${{ number_format($cartPreviewSubtotal, 2) }}</strong>
                                </div>
                                <a class="btn btn-block" href="{{ route('checkout') }}">Checkout</a>
                                <a class="cart-dropdown-view" href="{{ route('cart') }}">View cart</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <form class="search-form search-mobile" action="{{ route('shop') }}" method="get" role="search">
            <label class="sr-only" for="q-mobile">Search part number</label>
            <input id="q-mobile" type="search" name="q" value="{{ request('q') }}" placeholder="Search part # or cross ref" autocomplete="off">
            <button type="submit">Search</button>
        </form>

        {{-- Desktop nav --}}
        <nav class="nav nav-desktop" aria-label="Product categories">
            <div class="nav-inner">
                <a class="nav-link" href="{{ route('shop') }}">Shop All</a>
                @foreach($navCategories as $navCat)
                    @include('store.partials.nav-item', ['navCat' => $navCat])
                @endforeach
                @if(($navMoreCategories ?? collect())->isNotEmpty())
                    <div class="nav-item nav-more">
                        <button type="button" class="nav-link nav-more-btn" aria-haspopup="true">
                            More <span class="nav-caret" aria-hidden="true">▾</span>
                        </button>
                        <div class="nav-dropdown nav-more-dropdown" role="menu">
                            @foreach($navMoreCategories as $navCat)
                                <div class="nav-subitem {{ $navCat->children->isNotEmpty() ? 'has-children' : '' }}">
                                    <a href="{{ route('category.show', $navCat->slug) }}">
                                        {{ $navCat->menuLabel() }}
                                        @if($navCat->children->isNotEmpty())
                                            <span class="nav-arrow">›</span>
                                        @endif
                                    </a>
                                    @if($navCat->children->isNotEmpty())
                                        <div class="nav-subdropdown">
                                            @foreach($navCat->children as $child)
                                                <a href="{{ route('category.show', $child->slug) }}">{{ $child->menuLabel() }}</a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </nav>
    </header>

    {{-- Mobile drawer --}}
    <div class="mobile-nav-backdrop" id="mobileBackdrop" hidden></div>
    <nav class="mobile-nav" id="mobileNav" aria-label="Mobile menu" hidden>
        <div class="mobile-nav-head">
            <a href="{{ route('home') }}" class="mobile-nav-brand" aria-label="MTC Truck Parts home">
                MTC <span>TRUCK PARTS</span>
            </a>
            <button type="button" class="mobile-nav-close" id="menuClose" aria-label="Close menu">×</button>
        </div>

        <div class="mobile-nav-body">
            <div class="mobile-quick">
                <a class="mobile-quick-btn" href="{{ route('shop') }}">Shop all</a>
                <a class="mobile-quick-btn mobile-quick-ghost" href="{{ route('shop', ['in_stock' => 1]) }}">In stock</a>
            </div>

            <p class="mobile-nav-label">Categories</p>
            <div class="mobile-cats">
                @foreach(($navCategories ?? collect())->concat($navMoreCategories ?? collect()) as $navCat)
                    <details class="mobile-acc">
                        <summary>
                            <span>{{ $navCat->menuLabel() }}</span>
                            <span class="mobile-acc-chevron" aria-hidden="true"></span>
                        </summary>
                        <div class="mobile-acc-body">
                            <a class="mobile-acc-all" href="{{ route('category.show', $navCat->slug) }}">View all</a>
                            @foreach($navCat->children as $child)
                                <a href="{{ route('category.show', $child->slug) }}">{{ $child->menuLabel() }}</a>
                                @foreach($child->children as $grand)
                                    <a class="mobile-nested" href="{{ route('category.show', $grand->slug) }}">{{ $grand->menuLabel() }}</a>
                                @endforeach
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>

            <p class="mobile-nav-label">Help</p>
            <div class="mobile-help">
                <a href="{{ route('page.shipping') }}">Shipping</a>
                <a href="{{ route('page.returns') }}">Returns</a>
                <a href="{{ route('page.faq') }}">FAQs</a>
                <a href="{{ route('page.contact') }}">Contact</a>
                <a href="{{ route('page.about') }}">About</a>
                <a href="{{ route('page.privacy') }}">Privacy</a>
            </div>
        </div>

        <div class="mobile-nav-foot">
            <a class="btn btn-block" href="tel:+15868844156">Call (586) 884-4156</a>
        </div>
    </nav>

    @if(session('success'))
        <div class="flash flash-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="flash flash-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-cta">
            <div class="footer-cta-inner">
                <div>
                    <p class="footer-cta-kicker">Need the right part fast?</p>
                    <h2 class="footer-cta-title">Talk to U.S. sales — we look up SKUs &amp; cross refs</h2>
                </div>
                <div class="footer-cta-actions">
                    <a class="btn" href="tel:+15868844156">Call (586) 884-4156</a>
                    <a class="btn btn-ghost" href="{{ route('shop') }}">Shop parts</a>
                </div>
            </div>
        </div>

        <div class="footer-main">
            <div class="footer-inner">
                <div class="footer-brand">
                    <a href="{{ route('home') }}" class="footer-logo">MTC <span>TRUCK PARTS</span></a>
                    <p class="footer-tagline">Macomb Truck Center — heavy duty truck &amp; trailer parts for shops, fleets, and owner-operators nationwide.</p>
                    <div class="footer-contact">
                        <a href="tel:+15868844156">+1 (586) 884-4156</a>
                        <a href="mailto:info@mtctruckparts.com">info@mtctruckparts.com</a>
                        <span>14125 Industrial Center Dr</span>
                    </div>
                    <form class="footer-search" action="{{ route('shop') }}" method="get" role="search">
                        <label class="sr-only" for="footer-q">Search parts</label>
                        <input id="footer-q" type="search" name="q" placeholder="Search part # or cross ref" autocomplete="off">
                        <button type="submit">Search</button>
                    </form>
                </div>

                <div class="footer-col">
                    <h4>Shop</h4>
                    <ul>
                        <li><a href="{{ route('shop') }}">All products</a></li>
                        <li><a href="{{ route('shop', ['in_stock' => 1]) }}">In stock only</a></li>
                        @foreach(($navCategories ?? collect())->take(5) as $navCat)
                            <li><a href="{{ route('category.show', $navCat->slug) }}">{{ $navCat->menuLabel() }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="{{ route('page.shipping') }}">Shipping</a></li>
                        <li><a href="{{ route('page.returns') }}">Returns &amp; Core</a></li>
                        <li><a href="{{ route('page.faq') }}">FAQs</a></li>
                        <li><a href="{{ route('page.contact') }}">Contact</a></li>
                        <li><a href="{{ route('cart') }}">View cart</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Company</h4>
                    <ul>
                        <li><a href="{{ route('page.about') }}">About us</a></li>
                        <li><a href="{{ route('page.privacy') }}">Privacy Policy</a></li>
                    </ul>
                    <h4 class="footer-wh-title">Warehouses</h4>
                    <ul class="footer-warehouses">
                        <li><strong>Michigan</strong><span>Primary fulfillment</span></li>
                        <li><strong>Illinois</strong><span>Midwest shipping</span></li>
                        <li class="is-soon"><strong>Toledo, OH</strong><span>Coming soon</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom-bar">
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} Macomb Truck Center. All rights reserved.</span>
                <nav class="footer-legal" aria-label="Legal">
                    <a href="{{ route('page.privacy') }}">Privacy Policy</a>
                    <a href="{{ route('page.shipping') }}">Shipping</a>
                    <a href="{{ route('page.returns') }}">Returns</a>
                    <a href="{{ route('page.contact') }}">Contact</a>
                </nav>
            </div>
        </div>
    </footer>

    <div class="newsletter-modal" id="newsletterModal" hidden role="dialog" aria-modal="true" aria-labelledby="newsletterTitle">
        <div class="newsletter-backdrop" id="newsletterBackdrop"></div>
        <div class="newsletter-card">
            <button type="button" class="newsletter-close" id="newsletterClose" aria-label="Close">&times;</button>
            <div class="newsletter-form-view" id="newsletterFormView">
                <p class="newsletter-kicker">Limited offer</p>
                <h2 id="newsletterTitle">Join our newsletter</h2>
                <p class="newsletter-copy">Get fleet tips, new arrivals, and a welcome coupon for your next order.</p>
                <form id="newsletterForm">
                    <label class="sr-only" for="newsletterEmail">Email</label>
                    <input id="newsletterEmail" type="email" name="email" placeholder="Your email address" required autocomplete="email">
                    <button class="btn btn-block" type="submit" id="newsletterSubmit">Get my coupon</button>
                </form>
                <p class="newsletter-note">No spam. Unsubscribe anytime. See our <a href="{{ route('page.privacy') }}">Privacy Policy</a>.</p>
                <button type="button" class="newsletter-skip" id="newsletterSkip">No thanks</button>
            </div>
            <div class="newsletter-success-view" id="newsletterSuccessView" hidden>
                <p class="newsletter-kicker">You’re in</p>
                <h2>Your coupon is ready</h2>
                <p class="newsletter-copy" id="newsletterSuccessMsg">Thanks for joining!</p>
                <div class="newsletter-code" id="newsletterCodeBox" hidden>
                    <span>Code</span>
                    <strong id="newsletterCode"></strong>
                    <em id="newsletterCodeLabel"></em>
                </div>
                <a class="btn btn-block" href="{{ route('shop') }}">Shop now</a>
                <button type="button" class="newsletter-skip" id="newsletterDone">Close</button>
            </div>
        </div>
    </div>

    <div class="consent-banner" id="consentBanner" hidden role="dialog" aria-labelledby="consentTitle" aria-live="polite">
        <div class="consent-inner">
            <div class="consent-copy">
                <strong id="consentTitle">Privacy &amp; Cookies</strong>
                <p>We use cookies and collect information as described in our <a href="{{ route('page.privacy') }}">Privacy Policy</a>. By clicking Accept, you agree to our Privacy Policy and cookie use.</p>
            </div>
            <div class="consent-actions">
                <a class="btn btn-outline consent-link" href="{{ route('page.privacy') }}">Read policy</a>
                <button type="button" class="btn" id="consentAccept">I Accept</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var toggle = document.getElementById('menuToggle');
        var closeBtn = document.getElementById('menuClose');
        var nav = document.getElementById('mobileNav');
        var backdrop = document.getElementById('mobileBackdrop');

        function openMenu() {
            if (!nav) return;
            nav.hidden = false;
            if (backdrop) backdrop.hidden = false;
            document.body.classList.add('nav-open');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
        }
        function closeMenu() {
            if (!nav) return;
            nav.hidden = true;
            if (backdrop) backdrop.hidden = true;
            document.body.classList.remove('nav-open');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        }
        if (toggle && nav) {
            toggle.addEventListener('click', function () {
                if (nav.hidden) openMenu(); else closeMenu();
            });
            if (closeBtn) closeBtn.addEventListener('click', closeMenu);
            if (backdrop) backdrop.addEventListener('click', closeMenu);
        }

        var COOKIE_NAME = 'mtc_privacy_accepted';
        var COOKIE_DAYS = 365;

        function getCookie(name) {
            var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : '';
        }
        function setCookie(name, value, days) {
            var expires = '';
            if (days) {
                var d = new Date();
                d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
                expires = '; expires=' + d.toUTCString();
            }
            document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
        }

        var banner = document.getElementById('consentBanner');
        var acceptBtn = document.getElementById('consentAccept');
        if (banner && !getCookie(COOKIE_NAME)) {
            banner.hidden = false;
        }
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                setCookie(COOKIE_NAME, '1', COOKIE_DAYS);
                if (banner) banner.hidden = true;
            });
        }

        var header = document.querySelector('.site-header');
        var topbar = document.querySelector('.topbar');
        function updateStuck() {
            if (!header) return;
            var threshold = topbar ? topbar.offsetHeight : 0;
            header.classList.toggle('is-stuck', window.scrollY > threshold);
        }
        window.addEventListener('scroll', updateStuck, { passive: true });
        updateStuck();

        var cartWrap = document.getElementById('cartWrap');
        var cartToggle = document.getElementById('cartToggle');
        var cartDropdown = document.getElementById('cartDropdown');
        function closeCart() {
            if (!cartDropdown || !cartToggle || !cartWrap) return;
            cartDropdown.hidden = true;
            cartToggle.setAttribute('aria-expanded', 'false');
            cartWrap.classList.remove('is-open');
        }
        function openCart() {
            if (!cartDropdown || !cartToggle || !cartWrap) return;
            cartDropdown.hidden = false;
            cartToggle.setAttribute('aria-expanded', 'true');
            cartWrap.classList.add('is-open');
        }
        if (cartToggle && cartDropdown) {
            cartToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (cartDropdown.hidden) openCart(); else closeCart();
            });
            cartDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
            document.addEventListener('click', closeCart);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeCart();
                    closeNewsletter();
                }
            });
        }

        var NL_KEY = 'mtc_newsletter_done';
        var newsletterModal = document.getElementById('newsletterModal');
        var newsletterForm = document.getElementById('newsletterForm');
        var formView = document.getElementById('newsletterFormView');
        var successView = document.getElementById('newsletterSuccessView');

        function newsletterDone() {
            return getCookie(NL_KEY) === '1' || localStorage.getItem(NL_KEY) === '1';
        }
        function markNewsletterDone() {
            setCookie(NL_KEY, '1', 365);
            try { localStorage.setItem(NL_KEY, '1'); } catch (e) {}
        }
        function openNewsletter() {
            if (!newsletterModal || newsletterDone()) return;
            newsletterModal.hidden = false;
            document.body.classList.add('modal-open');
        }
        function closeNewsletter() {
            if (!newsletterModal) return;
            newsletterModal.hidden = true;
            document.body.classList.remove('modal-open');
            markNewsletterDone();
        }

        document.getElementById('newsletterClose')?.addEventListener('click', closeNewsletter);
        document.getElementById('newsletterSkip')?.addEventListener('click', closeNewsletter);
        document.getElementById('newsletterDone')?.addEventListener('click', closeNewsletter);
        document.getElementById('newsletterBackdrop')?.addEventListener('click', closeNewsletter);

        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var email = document.getElementById('newsletterEmail').value.trim();
                var btn = document.getElementById('newsletterSubmit');
                if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }
                fetch('{{ route('newsletter.subscribe') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ email: email })
                })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        alert((res.d && res.d.message) || (res.d && res.d.errors && res.d.errors.email && res.d.errors.email[0]) || 'Could not subscribe. Try again.');
                        if (btn) { btn.disabled = false; btn.textContent = 'Get my coupon'; }
                        return;
                    }
                    markNewsletterDone();
                    if (formView) formView.hidden = true;
                    if (successView) successView.hidden = false;
                    document.getElementById('newsletterSuccessMsg').textContent = res.d.message || 'Thanks!';
                    if (res.d.coupon_code) {
                        var box = document.getElementById('newsletterCodeBox');
                        document.getElementById('newsletterCode').textContent = res.d.coupon_code;
                        document.getElementById('newsletterCodeLabel').textContent = res.d.coupon_label || '';
                        if (box) box.hidden = false;
                    }
                })
                .catch(function () {
                    alert('Could not subscribe. Try again.');
                    if (btn) { btn.disabled = false; btn.textContent = 'Get my coupon'; }
                });
            });
        }

        if (!newsletterDone()) {
            setTimeout(openNewsletter, 4500);
        }
    })();
    </script>
</body>
</html>
