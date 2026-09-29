<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section>
            <x-slot name="heading">What this replaces from your WP plugins</x-slot>
            <ul class="list-disc pl-5 text-sm space-y-1">
                <li><strong>Price upload</strong> — like <code>sku-price-update-xlsx</code> / Product-Update-Price (SKU or Automann Part Number + Updated Price)</li>
                <li><strong>Stock upload</strong> — like <code>in stock out of stock</code> (SKU + qty / stock_status)</li>
                <li><strong>Products create/update</strong> — like <code>add-new-products-full-details</code> + <code>boulk-update-products</code></li>
                <li><strong>Content/SEO</strong> — like content tab / upload products via xlsx (empty cells skip)</li>
                <li><strong>Export SKU+price by brand</strong> — like your brand export plugin</li>
            </ul>
            <p class="mt-3 text-sm text-gray-500">Built for your real workflow: <strong>50k rows at once</strong>. Upload returns immediately; workers process 1,000-row chunks. Empty cells never overwrite. Keep <code>php artisan queue:work</code> running for large files.</p>
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">1) Bulk price by SKU</x-slot>
                <div class="mb-3 flex gap-2 flex-wrap">
                    <x-filament::button wire:click="downloadTemplate('price')" color="gray" size="sm">Template</x-filament::button>
                    <x-filament::button wire:click="downloadTemplate('automann_price')" color="gray" size="sm">Automann template</x-filament::button>
                </div>
                <form wire:submit="submitPrice" class="space-y-4">
                    {{ $this->priceForm }}
                    <x-filament::button type="submit">Queue price import</x-filament::button>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">2) Bulk stock by SKU</x-slot>
                <div class="mb-3">
                    <x-filament::button wire:click="downloadTemplate('stock')" color="gray" size="sm">Template</x-filament::button>
                </div>
                <form wire:submit="submitStock" class="space-y-4">
                    {{ $this->stockForm }}
                    <x-filament::button type="submit">Queue stock import</x-filament::button>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">3) Products create / update</x-slot>
                <div class="mb-3">
                    <x-filament::button wire:click="downloadTemplate('products')" color="gray" size="sm">Template</x-filament::button>
                </div>
                <form wire:submit="submitProducts" class="space-y-4">
                    {{ $this->productsForm }}
                    <x-filament::button type="submit">Queue product import</x-filament::button>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">4) Content / SEO update</x-slot>
                <div class="mb-3">
                    <x-filament::button wire:click="downloadTemplate('content')" color="gray" size="sm">Template</x-filament::button>
                </div>
                <form wire:submit="submitContent" class="space-y-4">
                    {{ $this->contentForm }}
                    <x-filament::button type="submit">Queue content import</x-filament::button>
                </form>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">5) Export SKU + price by brand</x-slot>
            <form wire:submit="downloadExport" class="space-y-4 max-w-xl">
                {{ $this->exportForm }}
                <x-filament::button type="submit" color="success">Download CSV</x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Recent import runs</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Updated</th>
                            <th>Created</th>
                            <th>Skipped</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->recentRuns as $run)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2">#{{ $run->id }}</td>
                                <td>{{ $run->type }}@if($run->dry_run) <span class="text-amber-600">(dry)</span>@endif</td>
                                <td>{{ $run->status }} ({{ $run->progressPercent() }}%)</td>
                                <td>{{ $run->processed_rows }}/{{ $run->total_rows }}</td>
                                <td>{{ $run->updated_rows }}</td>
                                <td>{{ $run->created_rows }}</td>
                                <td>{{ $run->skipped_rows }}</td>
                                <td class="truncate max-w-[180px]">{{ $run->original_filename }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-3 text-gray-500">No imports yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <a href="{{ \App\Filament\Pages\ImportHistory::getUrl() }}" class="text-primary-600 text-sm font-semibold">Full history →</a>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
