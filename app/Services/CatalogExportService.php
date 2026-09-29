<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ProductVariant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogExportService
{
    public function exportSkuPriceByBrand(?string $brandSlug = null): StreamedResponse
    {
        $filename = 'sku-price-'.($brandSlug ?: 'all').'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($brandSlug) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'price', 'sale_price', 'qty', 'stock_status', 'brand', 'title']);

            ProductVariant::query()
                ->with(['product.brand'])
                ->when($brandSlug, function ($q) use ($brandSlug) {
                    $q->whereHas('product.brand', fn ($b) => $b->where('slug', $brandSlug));
                })
                ->orderBy('sku')
                ->chunk(500, function ($variants) use ($out) {
                    foreach ($variants as $variant) {
                        fputcsv($out, [
                            $variant->sku,
                            $variant->price,
                            $variant->sale_price,
                            $variant->qty,
                            $variant->stock_status,
                            $variant->product?->brand?->name,
                            $variant->product?->name,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function brandsForFilter()
    {
        return Brand::where('is_active', true)->orderBy('name')->pluck('name', 'slug');
    }
}
