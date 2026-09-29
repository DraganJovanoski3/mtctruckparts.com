<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class CartService
{
    public const SESSION_KEY = 'mtc_cart';

    public function items(): Collection
    {
        $raw = collect(session(self::SESSION_KEY, []));
        if ($raw->isEmpty()) {
            return collect();
        }

        $variants = ProductVariant::with(['product.brand', 'product.primaryImage', 'product.categories'])
            ->whereIn('id', $raw->keys())
            ->get()
            ->keyBy('id');

        return $raw->map(function ($qty, $variantId) use ($variants) {
            $variant = $variants->get((int) $variantId);
            if (! $variant) {
                return null;
            }

            $qty = (int) $qty;

            return [
                'variant' => $variant,
                'product' => $variant->product,
                'qty' => $qty,
                'line_total' => round((float) $variant->price * $qty, 2),
            ];
        })->filter()->values();
    }

    public function add(int $variantId, int $qty = 1): void
    {
        $cart = session(self::SESSION_KEY, []);
        $cart[$variantId] = ($cart[$variantId] ?? 0) + max(1, $qty);
        session([self::SESSION_KEY => $cart]);
    }

    public function update(int $variantId, int $qty): void
    {
        $cart = session(self::SESSION_KEY, []);
        if ($qty <= 0) {
            unset($cart[$variantId]);
        } else {
            $cart[$variantId] = $qty;
        }
        session([self::SESSION_KEY => $cart]);
    }

    public function remove(int $variantId): void
    {
        $this->update($variantId, 0);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return collect(session(self::SESSION_KEY, []))->sum();
    }

    public function subtotal(): float
    {
        return round($this->items()->sum('line_total'), 2);
    }
}
