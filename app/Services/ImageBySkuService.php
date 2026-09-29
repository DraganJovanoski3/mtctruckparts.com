<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageBySkuService
{
    public function normalizeFilenameToSku(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/\s*\(\d+\)$/', '', $base) ?? $base;
        $base = preg_replace('/[_-]copy$/i', '', $base) ?? $base;

        return SpreadsheetReader::normalizeSku($base);
    }

    /**
     * @param  array<int, UploadedFile|string>  $files
     * @return array{attached: int, skipped: array<int, array{file: string, reason: string}>}
     */
    public function attachByFilenames(array $files, bool $replacePrimary = true): array
    {
        $attached = 0;
        $skipped = [];

        foreach ($files as $file) {
            $original = is_string($file) ? basename($file) : $file->getClientOriginalName();
            $sku = $this->normalizeFilenameToSku($original);
            if ($sku === '') {
                $skipped[] = ['file' => $original, 'reason' => 'Could not parse SKU from filename'];
                continue;
            }

            $variant = ProductVariant::where('sku', $sku)->first();
            if (! $variant) {
                // case-insensitive fallback
                $variant = ProductVariant::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();
            }
            if (! $variant || ! $variant->product) {
                $skipped[] = ['file' => $original, 'reason' => "SKU not found: {$sku}"];
                continue;
            }

            $path = is_string($file)
                ? $this->storeFromPath($file, $sku)
                : $file->storeAs('products/'.$variant->product_id, $this->safeName($sku, is_string($file) ? $file : $file->getClientOriginalName()), 'public');

            $this->setPrimaryImage($variant->product, $path, $replacePrimary);
            $attached++;
        }

        return compact('attached', 'skipped');
    }

    /**
     * Sheet rows with sku + optional image_url.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array{attached: int, skipped: array<int, array{sku: string, reason: string}>}
     */
    public function attachFromSheetRows(array $rows, bool $replacePrimary = true): array
    {
        $attached = 0;
        $skipped = [];
        $seen = [];

        foreach ($rows as $row) {
            $sku = SpreadsheetReader::normalizeSku($row['sku'] ?? '');
            if ($sku === '') {
                $skipped[] = ['sku' => '', 'reason' => 'Empty SKU'];
                continue;
            }
            $key = strtolower($sku);
            if (isset($seen[$key])) {
                $skipped[] = ['sku' => $sku, 'reason' => 'Duplicate SKU in sheet'];
                continue;
            }
            $seen[$key] = true;

            $url = $row['image_url'] ?? $row['image'] ?? $row['url'] ?? $row['photo'] ?? $row['photo_url'] ?? '';
            $url = trim((string) $url);
            if ($url === '') {
                $skipped[] = ['sku' => $sku, 'reason' => 'Empty image_url'];
                continue;
            }

            $variant = ProductVariant::where('sku', $sku)->first()
                ?: ProductVariant::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();

            if (! $variant?->product) {
                $skipped[] = ['sku' => $sku, 'reason' => 'SKU not found'];
                continue;
            }

            try {
                $path = $this->downloadToProduct($variant->product_id, $sku, $url);
                $this->setPrimaryImage($variant->product, $path, $replacePrimary);
                $attached++;
            } catch (\Throwable $e) {
                $skipped[] = ['sku' => $sku, 'reason' => 'Download failed: '.$e->getMessage()];
            }
        }

        return compact('attached', 'skipped');
    }

    public function exportMissingImages(?string $brandSlug = null): StreamedResponse
    {
        $filename = 'missing-images-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($brandSlug) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'title', 'brand', 'category', 'stock', 'price']);

            Product::query()
                ->published()
                ->with(['brand', 'defaultVariant', 'categories', 'primaryImage'])
                ->whereDoesntHave('images', fn ($q) => $q->where('is_primary', true))
                ->when($brandSlug, fn ($q) => $q->whereHas('brand', fn ($b) => $b->where('slug', $brandSlug)))
                ->orderBy('id')
                ->chunk(200, function ($products) use ($out) {
                    foreach ($products as $product) {
                        fputcsv($out, [
                            $product->sku,
                            $product->name,
                            $product->brand?->name,
                            $product->categories->first()?->name,
                            $product->defaultVariant?->stock_status,
                            $product->price,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function setPrimaryImage(Product $product, string $path, bool $replacePrimary): void
    {
        if ($replacePrimary) {
            ProductImage::where('product_id', $product->id)->where('is_primary', true)->update(['is_primary' => false]);
        }

        ProductImage::updateOrCreate(
            ['product_id' => $product->id, 'path' => $path],
            [
                'alt' => $product->name,
                'sort_order' => 0,
                'is_primary' => true,
            ]
        );
    }

    protected function storeFromPath(string $absoluteOrRelative, string $sku): string
    {
        $absolute = is_file($absoluteOrRelative)
            ? $absoluteOrRelative
            : Storage::disk('local')->path($absoluteOrRelative);

        $ext = pathinfo($absolute, PATHINFO_EXTENSION) ?: 'jpg';
        $dest = 'products/'.Str::slug($sku).'/'.$this->safeName($sku, $sku.'.'.$ext);
        Storage::disk('public')->put($dest, file_get_contents($absolute));

        return $dest;
    }

    protected function downloadToProduct(int $productId, string $sku, string $url): string
    {
        $response = Http::timeout(30)->withHeaders([
            'User-Agent' => 'MTC-ImageImport/1.0',
        ])->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status());
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower(preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $ext = 'jpg';
        }

        $dest = 'products/'.$productId.'/'.$this->safeName($sku, $sku.'.'.$ext);
        Storage::disk('public')->put($dest, $response->body());

        return $dest;
    }

    protected function safeName(string $sku, string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: 'jpg');

        return Str::slug($sku).'_'.Str::random(6).'.'.$ext;
    }
}
