<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request, CouponService $coupons): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
        ]);

        $email = strtolower(trim($data['email']));
        $existing = NewsletterSubscriber::query()->where('email', $email)->first();
        if ($existing) {
            if ($existing->coupon_code) {
                session([CouponService::SESSION_KEY => $existing->coupon_code]);
            }

            return response()->json([
                'ok' => true,
                'already' => true,
                'message' => 'You are already subscribed.',
                'coupon_code' => $existing->coupon_code,
                'coupon_label' => $existing->coupon?->label(),
            ]);
        }

        $welcome = $coupons->newsletterWelcomeCoupon();

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => $email,
            'coupon_id' => $welcome?->id,
            'coupon_code' => $welcome?->code,
            'source' => 'popup',
            'ip' => $request->ip(),
            'subscribed_at' => now(),
        ]);

        if ($welcome) {
            session([CouponService::SESSION_KEY => $welcome->code]);
        }

        return response()->json([
            'ok' => true,
            'already' => false,
            'message' => $welcome
                ? 'Thanks for joining! Here is your coupon.'
                : 'Thanks for joining our newsletter.',
            'coupon_code' => $subscriber->coupon_code,
            'coupon_label' => $welcome?->label(),
        ]);
    }
}
