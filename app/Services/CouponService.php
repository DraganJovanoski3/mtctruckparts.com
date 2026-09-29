<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public const SESSION_KEY = 'mtc_coupon';

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();
    }

    public function newsletterWelcomeCoupon(): ?Coupon
    {
        return Coupon::query()
            ->where('is_newsletter_welcome', true)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    public function applied(): ?Coupon
    {
        $code = session(self::SESSION_KEY);
        if (! $code) {
            return null;
        }

        return $this->findByCode($code);
    }

    public function apply(string $code, Collection $items, float $subtotal, ?string $email = null): Coupon
    {
        $coupon = $this->findByCode($code);
        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon code is invalid.']);
        }

        $this->assertValid($coupon, $items, $subtotal, $email);
        session([self::SESSION_KEY => $coupon->code]);

        return $coupon;
    }

    public function remove(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function discountAmount(Coupon $coupon, Collection $items, float $subtotal): float
    {
        $eligibleSubtotal = $this->eligibleSubtotal($coupon, $items);
        if ($eligibleSubtotal <= 0) {
            return 0;
        }

        $discount = match ($coupon->discount_type) {
            'percent' => round($eligibleSubtotal * ((float) $coupon->amount / 100), 2),
            'fixed_cart' => min((float) $coupon->amount, $subtotal),
            'fixed_product' => $this->fixedProductDiscount($coupon, $items),
            default => 0,
        };

        if ($coupon->maximum_amount !== null && (float) $coupon->maximum_amount > 0) {
            $discount = min($discount, (float) $coupon->maximum_amount);
        }

        return min(max($discount, 0), $subtotal);
    }

    public function assertValid(Coupon $coupon, Collection $items, float $subtotal, ?string $email = null): void
    {
        if (! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not active.']);
        }

        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not valid yet.']);
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has expired.']);
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has reached its usage limit.']);
        }

        if ($coupon->minimum_amount !== null && $subtotal < (float) $coupon->minimum_amount) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Minimum cart amount of $'.number_format((float) $coupon->minimum_amount, 2).' required.',
            ]);
        }

        if ($email && ! empty($coupon->email_restrictions)) {
            $allowed = collect($coupon->email_restrictions)->map(fn ($e) => strtolower(trim($e)));
            if (! $allowed->contains(strtolower($email))) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon is not valid for your email.']);
            }
        }

        if ($email && $coupon->usage_limit_per_user) {
            $usedByEmail = \App\Models\Order::query()
                ->where('coupon_id', $coupon->id)
                ->where('email', $email)
                ->count();
            if ($usedByEmail >= $coupon->usage_limit_per_user) {
                throw ValidationException::withMessages(['coupon_code' => 'You have already used this coupon.']);
            }
        }

        if ($this->eligibleSubtotal($coupon, $items) <= 0 && in_array($coupon->discount_type, ['percent', 'fixed_product'], true)) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon does not apply to items in your cart.']);
        }
    }

    public function eligibleSubtotal(Coupon $coupon, Collection $items): float
    {
        $productIds = collect($coupon->product_ids ?? []);
        $excludeProductIds = collect($coupon->exclude_product_ids ?? []);
        $categoryIds = collect($coupon->category_ids ?? []);
        $excludeCategoryIds = collect($coupon->exclude_category_ids ?? []);

        return round($items->filter(function ($item) use ($productIds, $excludeProductIds, $categoryIds, $excludeCategoryIds) {
            $product = $item['product'];
            $productId = $product->id;

            if ($excludeProductIds->isNotEmpty() && $excludeProductIds->contains($productId)) {
                return false;
            }

            if ($productIds->isNotEmpty() && ! $productIds->contains($productId)) {
                return false;
            }

            $itemCategoryIds = $product->categories?->pluck('id') ?? collect();

            if ($excludeCategoryIds->isNotEmpty() && $itemCategoryIds->intersect($excludeCategoryIds)->isNotEmpty()) {
                return false;
            }

            if ($categoryIds->isNotEmpty() && $itemCategoryIds->intersect($categoryIds)->isEmpty()) {
                return false;
            }

            return true;
        })->sum('line_total'), 2);
    }

    protected function fixedProductDiscount(Coupon $coupon, Collection $items): float
    {
        $eligible = $items->filter(fn ($item) => $this->eligibleSubtotal($coupon, collect([$item])) > 0);
        if ($coupon->limit_usage_to_x_items) {
            $eligible = $eligible->take((int) $coupon->limit_usage_to_x_items);
        }

        $qty = $eligible->sum('qty');

        return round(min((float) $coupon->amount * max($qty, 1), $eligible->sum('line_total')), 2);
    }
}
