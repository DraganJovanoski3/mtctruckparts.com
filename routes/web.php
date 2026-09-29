<?php

use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'shop'])->name('shop');
Route::get('/category/{slug}', [StorefrontController::class, 'category'])->name('category.show');
Route::get('/product/{slug}', [StorefrontController::class, 'product'])->name('product.show');

Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');
Route::post('/cart/add', [StorefrontController::class, 'addToCart'])->name('cart.add');
Route::post('/cart/update', [StorefrontController::class, 'updateCart'])->name('cart.update');
Route::post('/cart/coupon', [StorefrontController::class, 'applyCoupon'])->name('cart.coupon');
Route::post('/cart/coupon/remove', [StorefrontController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [StorefrontController::class, 'placeOrder'])->name('checkout.place');
Route::get('/checkout/thanks/{order}', [StorefrontController::class, 'thanks'])->name('checkout.thanks');

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

Route::get('/about', [PageController::class, 'about'])->name('page.about');
Route::get('/shipping', [PageController::class, 'shipping'])->name('page.shipping');
Route::get('/returns', [PageController::class, 'returns'])->name('page.returns');
Route::get('/contact', [PageController::class, 'contact'])->name('page.contact');
Route::get('/faq', [PageController::class, 'faq'])->name('page.faq');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('page.privacy');
