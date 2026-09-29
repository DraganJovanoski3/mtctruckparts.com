<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Like your WP “update images from sku” plugin</x-slot>
            <ul class="list-disc pl-5 text-sm space-y-1">
                <li>Upload files named as the SKU (<code>ABC-123.jpg</code>)</li>
                <li>Or import a sheet with <code>sku</code> + <code>image_url</code></li>
                <li>Export products missing a primary image</li>
            </ul>
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">1) Match files by SKU filename</x-slot>
                <form wire:submit="matchFilenames" class="space-y-4">
                    {{ $this->filesForm }}
                    <x-filament::button type="submit">Attach images</x-filament::button>
                </form>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">2) Import from sheet URLs</x-slot>
                <div class="mb-3">
                    <x-filament::button wire:click="downloadTemplate" color="gray" size="sm">Template</x-filament::button>
                </div>
                <form wire:submit="importUrls" class="space-y-4">
                    {{ $this->urlsForm }}
                    <x-filament::button type="submit">Import URLs</x-filament::button>
                </form>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">3) Export missing images</x-slot>
            <form wire:submit="exportMissing" class="space-y-4 max-w-xl">
                {{ $this->exportForm }}
                <x-filament::button type="submit" color="success">Download missing CSV</x-filament::button>
            </form>
        </x-filament::section>

        @if(count($lastSkipped))
            <x-filament::section>
                <x-slot name="heading">Skipped ({{ count($lastSkipped) }})</x-slot>
                <div class="overflow-x-auto max-h-80">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2">File / SKU</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lastSkipped as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-1">{{ $row['file'] }}</td>
                                    <td>{{ $row['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
