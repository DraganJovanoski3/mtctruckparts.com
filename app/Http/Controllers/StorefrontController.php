<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View
    {
        $featured = Product::published()
            ->with(['brand', 'defaultVariant', 'primaryImage', 'categories'])
            ->where('is_featured', true)
            ->latest()
            ->take(8)
            ->get();

        $latest = Product::published()
            ->with(['brand', 'defaultVariant', 'primaryImage'])
            ->latest()
            ->take(8)
            ->get();

        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->where('name', 'not like', '%>%')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn ($c) => $c->children->isNotEmpty())
            ->values();

        $brands = Brand::where('is_active', true)->orderBy('name')->take(16)->get();

        return view('store.home', compact('featured', 'latest', 'categories', 'brands'));
    }

    public function shop(Request $request): View
    {
        $query = Product::published()->with(['brand', 'defaultVariant', 'primaryImage']);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('part_number', 'like', "%{$search}%")
                    ->orWhereHas('defaultVariant', fn ($v) => $v->where('sku', 'like', "%{$search}%"))
                    ->orWhereHas('crossReferences', fn ($c) => $c->where('ref_number', 'like', "%{$search}%"));
            });
        }

        if ($brand = $request->get('brand')) {
            $query->whereHas('brand', fn ($q) => $q->where('slug', $brand));
        }

        if ($category = $request->get('category')) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $category));
        }

        if ($request->boolean('in_stock')) {
            $query->whereHas('defaultVariant', fn ($q) => $q->where('qty', '>', 0));
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->join('product_variants', function ($join) {
                $join->on('products.id', '=', 'product_variants.product_id')
                    ->where('product_variants.is_default', true);
            })->orderBy('product_variants.price')->select('products.*'),
            'price_desc' => $query->join('product_variants', function ($join) {
                $join->on('products.id', '=', 'product_variants.product_id')
                    ->where('product_variants.is_default', true);
            })->orderByDesc('product_variants.price')->select('products.*'),
            'name' => $query->orderBy('name'),
            default => $query->latest('products.created_at'),
        };

        $products = $query->paginate(24)->withQueryString();
        $categories = Category::where('is_active', true)->whereNull('parent_id')->orderBy('sort_order')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('store.shop', compact('products', 'categories', 'brands', 'search', 'sort'));
    }

    public function category(string $slug): View
    {
        $category = Category::where('slug', $slug)->where('is_active', true)
            ->with(['childrenRecursive'])
            ->firstOrFail();

        $ids = collect([$category->id]);
        $walk = function ($cats) use (&$walk, &$ids) {
            foreach ($cats as $cat) {
                $ids->push($cat->id);
                if ($cat->relationLoaded('childrenRecursive') && $cat->childrenRecursive->isNotEmpty()) {
                    $walk($cat->childrenRecursive);
                } elseif ($cat->children->isNotEmpty()) {
                    $walk($cat->children);
                }
            }
        };
        $walk($category->childrenRecursive);

        $products = Product::published()
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids->unique()->all()))
            ->with(['brand', 'defaultVariant', 'primaryImage'])
            ->latest()
            ->paginate(24);

        $categories = Category::where('is_active', true)->whereNull('parent_id')->where('name', 'not like', '%>%')->orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();
        $search = '';
        $sort = 'newest';

        return view('store.shop', compact('products', 'categories', 'brands', 'category', 'search', 'sort'));
    }

    public function product(string $slug): View
    {
        $product = Product::published()
            ->where('slug', $slug)
            ->with(['brand', 'variants', 'crossReferences', 'categories', 'images', 'tags'])
            ->firstOrFail();

        $related = Product::published()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $q->where('brand_id', $product->brand_id)
                    ->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $product->categories->pluck('id')));
            })
            ->with(['brand', 'defaultVariant', 'primaryImage'])
            ->take(4)
            ->get();

        return view('store.product', compact('product', 'related'));
    }

    public function cart(CartService $cart, CouponService $coupons): View
    {
        $items = $cart->items();
        $subtotal = $cart->subtotal();
        $coupon = $coupons->applied();
        $discount = $coupon ? $coupons->discountAmount($coupon, $items, $subtotal) : 0;

        return view('store.cart', [
            'items' => $items,
            'subtotal' => $subtotal,
            'coupon' => $coupon,
            'discount' => $discount,
            'total' => max($subtotal - $discount, 0),
        ]);
    }

    public function addToCart(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $cart->add((int) $data['variant_id'], (int) ($data['qty'] ?? 1));

        return back()->with('success', 'Added to cart.');
    }

    public function updateCart(Request $request, CartService $cart)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $cart->update((int) $data['variant_id'], (int) $data['qty']);

        return back()->with('success', 'Cart updated.');
    }

    public function applyCoupon(Request $request, CartService $cart, CouponService $coupons)
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:50'],
        ]);

        $coupons->apply($data['coupon_code'], $cart->items(), $cart->subtotal());

        return back()->with('success', 'Coupon applied.');
    }

    public function removeCoupon(CouponService $coupons)
    {
        $coupons->remove();

        return back()->with('success', 'Coupon removed.');
    }

    public function checkout(CartService $cart, CouponService $coupons)
    {
        if ($cart->items()->isEmpty()) {
            return redirect()->route('cart');
        }

        $items = $cart->items();
        $subtotal = $cart->subtotal();
        $coupon = $coupons->applied();
        $discount = $coupon ? $coupons->discountAmount($coupon, $items, $subtotal) : 0;

        return view('store.checkout', [
            'items' => $items,
            'subtotal' => $subtotal,
            'coupon' => $coupon,
            'discount' => $discount,
            'total' => max($subtotal - $discount, 0),
        ]);
    }

    public function placeOrder(Request $request, CartService $cart, CouponService $coupons)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_state' => ['required', 'string', 'max:100'],
            'shipping_zip' => ['required', 'string', 'max:20'],
            'same_as_shipping' => ['nullable', 'boolean'],
            'billing_first_name' => ['nullable', 'string', 'max:100'],
            'billing_last_name' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_zip' => ['nullable', 'string', 'max:20'],
        ]);

        $sameAsShipping = $request->boolean('same_as_shipping');

        $shipping = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['shipping_address'],
            'city' => $data['shipping_city'],
            'state' => $data['shipping_state'],
            'zip' => $data['shipping_zip'],
        ];

        if ($sameAsShipping) {
            $billing = $shipping;
        } else {
            $request->validate([
                'billing_first_name' => ['required', 'string', 'max:100'],
                'billing_last_name' => ['required', 'string', 'max:100'],
                'billing_address' => ['required', 'string', 'max:255'],
                'billing_city' => ['required', 'string', 'max:100'],
                'billing_state' => ['required', 'string', 'max:100'],
                'billing_zip' => ['required', 'string', 'max:20'],
            ]);

            $billing = [
                'first_name' => $data['billing_first_name'],
                'last_name' => $data['billing_last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['billing_address'],
                'city' => $data['billing_city'],
                'state' => $data['billing_state'],
                'zip' => $data['billing_zip'],
            ];
        }

        $items = $cart->items();
        abort_if($items->isEmpty(), 422, 'Cart is empty');

        $subtotal = $cart->subtotal();
        $coupon = $coupons->applied();
        $discount = 0;

        if ($coupon) {
            $coupons->assertValid($coupon, $items, $subtotal, $data['email']);
            $discount = $coupons->discountAmount($coupon, $items, $subtotal);
        }

        $grandTotal = max($subtotal - $discount, 0);

        $order = \App\Models\Order::create([
            'number' => 'MTC-'.now()->format('YmdHis').'-'.random_int(100, 999),
            'email' => $data['email'],
            'status' => 'pending',
            'payment_status' => 'awaiting_authorize_net',
            'subtotal' => $subtotal,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => $discount,
            'grand_total' => $grandTotal,
            'coupon_code' => $coupon?->code,
            'coupon_id' => $coupon?->id,
            'billing_address' => $billing,
            'shipping_address' => $shipping,
        ]);

        foreach ($items as $item) {
            $order->items()->create([
                'product_variant_id' => $item['variant']->id,
                'sku' => $item['variant']->sku,
                'name' => $item['product']->name,
                'qty' => $item['qty'],
                'unit_price' => $item['variant']->price,
                'line_total' => $item['line_total'],
            ]);
        }

        if ($coupon) {
            $coupon->increment('used_count');
        }

        $cart->clear();
        $coupons->remove();

        return redirect()->route('checkout.thanks', $order)->with('success', 'Order placed. Authorize.net payment will be wired next.');
    }

    public function thanks(\App\Models\Order $order): View
    {
        return view('store.thanks', compact('order'));
    }
}
