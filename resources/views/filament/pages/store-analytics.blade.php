<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Date range</x-slot>
            <form class="max-w-4xl">
                {{ $this->form }}
            </form>
        </x-filament::section>

        @php $s = $this->stats; @endphp

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Revenue</p>
                <p class="mt-1 text-3xl font-bold tracking-tight">{{ $this->money($s['revenue']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $s['orders_count'] }} orders · avg {{ $this->money($s['avg_order']) }}</p>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Units sold</p>
                <p class="mt-1 text-3xl font-bold tracking-tight">{{ number_format($s['units_sold']) }}</p>
                <p class="mt-1 text-xs text-gray-500">Line items across orders</p>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Tax collected</p>
                <p class="mt-1 text-3xl font-bold tracking-tight">{{ $this->money($s['tax']) }}</p>
                <p class="mt-1 text-xs text-gray-500">Shipping {{ $this->money($s['shipping']) }}</p>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Discounts / coupons</p>
                <p class="mt-1 text-3xl font-bold tracking-tight">{{ $this->money($s['discounts']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $s['coupon']['orders_with_coupon'] }} orders used a coupon</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Merchandise subtotal</p>
                <p class="mt-1 text-2xl font-bold">{{ $this->money($s['subtotal']) }}</p>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Unique coupon codes used</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($s['coupon']['unique_codes']) }}</p>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Active coupons</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($s['active_coupons']) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ number_format($s['coupon_redemptions_all_time']) }} redemptions all-time</p>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 p-5">
                <p class="text-sm text-gray-500">Newsletter signups</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($s['newsletter_signups']) }}</p>
                <p class="mt-1 text-xs text-gray-500">In selected range</p>
            </div>
        </div>

        <x-filament::section>
            <x-slot name="heading">Daily revenue</x-slot>
            @php
                $max = max(1, (float) collect($s['daily'])->max('revenue'));
            @endphp
            <div class="flex items-end gap-1 h-40 overflow-x-auto pb-2">
                @foreach($s['daily'] as $day)
                    @php $h = max(2, (int) round(($day->revenue / $max) * 100)); @endphp
                    <div class="flex flex-col items-center justify-end min-w-[18px] flex-1 gap-1" title="{{ $day->day }}: {{ $this->money($day->revenue) }} ({{ $day->orders_count }} orders)">
                        <div class="w-full rounded-t bg-primary-600 dark:bg-primary-500" style="height: {{ $h }}%"></div>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500">Hover bars for day totals. Range follows the filter above.</p>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Best sellers (units)</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2 pr-2">SKU</th>
                                <th class="py-2 pr-2">Product</th>
                                <th class="py-2 pr-2 text-right">Units</th>
                                <th class="py-2 text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($s['top_by_qty'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-2 font-mono text-xs">{{ $row->sku }}</td>
                                    <td class="py-2 pr-2">{{ \Illuminate\Support\Str::limit($row->name, 42) }}</td>
                                    <td class="py-2 pr-2 text-right font-semibold">{{ number_format($row->units_sold) }}</td>
                                    <td class="py-2 text-right">{{ $this->money((float) $row->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-gray-500">No sales in this range yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Top revenue products</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2 pr-2">SKU</th>
                                <th class="py-2 pr-2">Product</th>
                                <th class="py-2 pr-2 text-right">Units</th>
                                <th class="py-2 text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($s['top_by_revenue'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-2 font-mono text-xs">{{ $row->sku }}</td>
                                    <td class="py-2 pr-2">{{ \Illuminate\Support\Str::limit($row->name, 42) }}</td>
                                    <td class="py-2 pr-2 text-right">{{ number_format($row->units_sold) }}</td>
                                    <td class="py-2 text-right font-semibold">{{ $this->money((float) $row->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-4 text-gray-500">No sales in this range yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Coupon performance</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">Code</th>
                                <th class="py-2 text-right">Uses</th>
                                <th class="py-2 text-right">Discount given</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($s['top_coupons'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 font-semibold">{{ $row->coupon_code }}</td>
                                    <td class="py-2 text-right">{{ number_format($row->uses) }}</td>
                                    <td class="py-2 text-right">{{ $this->money((float) $row->discount_total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-4 text-gray-500">No coupons used in this range.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Orders by status / payment</x-slot>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Status</p>
                        <ul class="space-y-2 text-sm">
                            @forelse($s['by_status'] as $row)
                                <li class="flex justify-between gap-2 border-b border-gray-100 dark:border-gray-800 py-1.5">
                                    <span>{{ $row->status }}</span>
                                    <span class="font-semibold">{{ $row->total }} · {{ $this->money((float) $row->revenue) }}</span>
                                </li>
                            @empty
                                <li class="text-gray-500">No orders.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Payment</p>
                        <ul class="space-y-2 text-sm">
                            @forelse($s['by_payment'] as $row)
                                <li class="flex justify-between gap-2 border-b border-gray-100 dark:border-gray-800 py-1.5">
                                    <span>{{ $row->payment_status }}</span>
                                    <span class="font-semibold">{{ $row->total }} · {{ $this->money((float) $row->revenue) }}</span>
                                </li>
                            @empty
                                <li class="text-gray-500">No orders.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
