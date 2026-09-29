<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Price updater = Automann tier markup</x-slot>
            <p class="text-sm text-gray-500 mb-3">
                Drop your Automann <strong>CSV / XLSX / XML</strong> (same files as the WooCommerce price-update plugin + Python markup tool).
                We calculate <code>web_price = Dist. Price List × multiplier</code>, then queue catalog updates by SKU / Automann Part Number.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">Cost bracket</th>
                            <th>Increase</th>
                            <th>Multiplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->tiers as $tier)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2">{{ $tier['bracket'] }}</td>
                                <td>{{ $tier['increase'] }}</td>
                                <td>{{ $tier['multiplier'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Upload &amp; run</x-slot>
            <form wire:submit="run" class="space-y-4 max-w-2xl">
                {{ $this->form }}
                <x-filament::button type="submit">Markup &amp; update prices</x-filament::button>
            </form>
            @if($lastStats)
                <p class="mt-3 text-sm">
                    Last run: processed {{ $lastStats['processed'] ?? 0 }},
                    marked up {{ $lastStats['updated'] ?? 0 }},
                    skipped empty {{ $lastStats['skipped_empty'] ?? 0 }}
                    @if(($lastStats['skipped_no_sku'] ?? 0) > 0)
                        , no SKU {{ $lastStats['skipped_no_sku'] }}
                    @endif
                    .
                    @if($lastImportRunId)
                        Import run <a class="underline font-semibold" href="{{ \App\Filament\Pages\ImportHistory::getUrl() }}">#{{ $lastImportRunId }}</a>
                    @endif
                </p>
            @endif
            <p class="mt-2 text-xs text-gray-500">For large files keep <code>php artisan queue:work</code> running. Progress is in <strong>Bulk tools → Import history</strong>.</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
