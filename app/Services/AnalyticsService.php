<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(?Carbon $from = null, ?Carbon $to = null): array
    {
        $orders = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->whereNotIn('status', ['cancelled', 'failed']);

        $orderIds = (clone $orders)->pluck('id');

        $totals = (clone $orders)->selectRaw('
            COUNT(*) as orders_count,
            COALESCE(SUM(grand_total), 0) as revenue,
            COALESCE(SUM(subtotal), 0) as subtotal,
            COALESCE(SUM(shipping_total), 0) as shipping,
            COALESCE(SUM(tax_total), 0) as tax,
            COALESCE(SUM(discount_total), 0) as discounts,
            COALESCE(AVG(grand_total), 0) as avg_order
        ')->first();

        $couponOrders = (clone $orders)
            ->whereNotNull('coupon_code')
            ->where('coupon_code', '!=', '');

        $couponStats = [
            'orders_with_coupon' => (clone $couponOrders)->count(),
            'discount_from_coupons' => (float) (clone $couponOrders)->sum('discount_total'),
            'unique_codes' => (int) (clone $couponOrders)->selectRaw('COUNT(DISTINCT coupon_code) as c')->value('c'),
        ];

        $topCoupons = (clone $couponOrders)
            ->select('coupon_code', DB::raw('COUNT(*) as uses'), DB::raw('SUM(discount_total) as discount_total'))
            ->groupBy('coupon_code')
            ->orderByDesc('uses')
            ->limit(10)
            ->get();

        if ($orderIds->isEmpty()) {
            $topByQty = collect();
            $topByRevenue = collect();
            $unitsSold = 0;
        } else {
            $topByQty = OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->select('sku', 'name', DB::raw('SUM(qty) as units_sold'), DB::raw('SUM(line_total) as revenue'))
                ->groupBy('sku', 'name')
                ->orderByDesc('units_sold')
                ->limit(15)
                ->get();

            $topByRevenue = OrderItem::query()
                ->whereIn('order_id', $orderIds)
                ->select('sku', 'name', DB::raw('SUM(qty) as units_sold'), DB::raw('SUM(line_total) as revenue'))
                ->groupBy('sku', 'name')
                ->orderByDesc('revenue')
                ->limit(15)
                ->get();

            $unitsSold = (int) OrderItem::query()->whereIn('order_id', $orderIds)->sum('qty');
        }
        $byStatus = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(grand_total) as revenue'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $byPayment = Order::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->select('payment_status', DB::raw('COUNT(*) as total'), DB::raw('SUM(grand_total) as revenue'))
            ->groupBy('payment_status')
            ->orderByDesc('total')
            ->get();

        $daily = $this->dailyRevenue($from, $to);

        $newsletter = NewsletterSubscriber::query()
            ->when($from, fn ($q) => $q->where('subscribed_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('subscribed_at', '<=', $to->copy()->endOfDay()))
            ->count();

        $activeCoupons = Coupon::query()->where('is_active', true)->count();
        $couponRedemptions = Coupon::query()->sum('used_count');

        return [
            'orders_count' => (int) ($totals->orders_count ?? 0),
            'revenue' => (float) ($totals->revenue ?? 0),
            'subtotal' => (float) ($totals->subtotal ?? 0),
            'shipping' => (float) ($totals->shipping ?? 0),
            'tax' => (float) ($totals->tax ?? 0),
            'discounts' => (float) ($totals->discounts ?? 0),
            'avg_order' => (float) ($totals->avg_order ?? 0),
            'coupon' => $couponStats,
            'top_coupons' => $topCoupons,
            'top_by_qty' => $topByQty,
            'top_by_revenue' => $topByRevenue,
            'by_status' => $byStatus,
            'by_payment' => $byPayment,
            'daily' => $daily,
            'newsletter_signups' => $newsletter,
            'active_coupons' => $activeCoupons,
            'coupon_redemptions_all_time' => (int) $couponRedemptions,
            'units_sold' => $unitsSold,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    protected function dailyRevenue(?Carbon $from, ?Carbon $to): Collection
    {
        $to = $to?->copy()->endOfDay() ?? now()->endOfDay();
        $from = $from?->copy()->startOfDay() ?? $to->copy()->subDays(29)->startOfDay();

        $driver = DB::connection()->getDriverName();
        $dayExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $rows = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->selectRaw("{$dayExpr} as day, COUNT(*) as orders_count, COALESCE(SUM(grand_total), 0) as revenue")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $days = collect();
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);
            $days->push((object) [
                'day' => $key,
                'orders_count' => (int) ($row->orders_count ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
            ]);
            $cursor->addDay();
        }

        return $days;
    }
}
