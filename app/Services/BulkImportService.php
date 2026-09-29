<?php

namespace App\Services;

use App\Jobs\PrepareImportRunJob;
use App\Jobs\ProcessImportChunkJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CrossReference;
use App\Models\ImportRun;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tag;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BulkImportService
{
    /** Rows per queue job — sized for ~50k catalogs (≈50–100 jobs). */
    public const CHUNK_SIZE = 1000;

    /** Inline only tiny smoke tests; real 50k runs always queue. */
    public const SYNC_MAX_ROWS = 50;

    public function startFromUpload(string $type, string $absolutePath, string $originalName, string $mode = 'update', bool $dryRun = false): ImportRun
    {
        $stored = 'imports/'.now()->format('Ymd_His').'_'.Str::random(8).'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
        Storage::disk('local')->put($stored, file_get_contents($absolutePath));

        $run = ImportRun::create([
            'user_id' => Auth::id(),
            'type' => $type,
            'status' => 'queued',
            'original_filename' => $originalName,
            'stored_path' => $stored,
            'mode' => $mode,
            'dry_run' => $dryRun,
            'total_rows' => 0,
            'started_at' => now(),
        ]);

        // Fast path: tiny files for local testing
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true) && filesize($absolutePath) < 200_000) {
            $data = SpreadsheetReader::read($absolutePath);
            if (count($data['rows']) <= self::SYNC_MAX_ROWS) {
                $jsonl = $this->rowsToJsonlPath($run, $data['rows']);
                $run->update([
                    'stored_path' => $jsonl,
                    'total_rows' => count($data['rows']),
                    'status' => 'processing',
                ]);
                $this->processChunkByOffset($run, 0, self::CHUNK_SIZE, true);

                return $run->fresh();
            }
        }

        // 50k path: prepare file + dispatch chunks in background (no HTTP timeout)
        PrepareImportRunJob::dispatch($run->id);

        return $run;
    }

    public function prepareAndDispatch(ImportRun $run): void
    {
        $source = Storage::disk('local')->path($run->stored_path);
        $data = SpreadsheetReader::read($source);
        $rows = $data['rows'];
        $total = count($rows);

        $jsonlRelative = $this->rowsToJsonlPath($run, $rows);
        unset($rows, $data);

        $run->update([
            'stored_path' => $jsonlRelative,
            'total_rows' => $total,
            'status' => $total === 0 ? 'completed' : 'processing',
            'finished_at' => $total === 0 ? now() : null,
        ]);

        if ($total === 0) {
            return;
        }

        // Warm SKU map once for price/stock (huge speedup at 50k)
        if (in_array($run->type, ['price', 'stock'], true)) {
            $this->warmSkuMap($run->id);
        }

        for ($offset = 0; $offset < $total; $offset += self::CHUNK_SIZE) {
            ProcessImportChunkJob::dispatch(
                $run->id,
                $offset,
                self::CHUNK_SIZE,
                ($offset + self::CHUNK_SIZE) >= $total,
            );
        }
    }

    public function processChunkByOffset(ImportRun $run, int $offset, int $limit, bool $isLastChunk = false): void
    {
        $rows = $this->readJsonlSlice($run->stored_path, $offset, $limit);
        $this->processChunk($run, $rows, $isLastChunk);
    }

    public function processChunk(ImportRun $run, array $rows, bool $isLastChunk): void
    {
        if (in_array($run->status, ['queued', 'failed'], true)) {
            $run->update(['status' => 'processing', 'error_message' => null]);
        }

        $reportLines = [];
        $updated = 0;
        $created = 0;
        $skipped = 0;
        $failed = 0;

        // Price/stock: batch-friendly path with cached SKU→id map
        $skuMap = in_array($run->type, ['price', 'stock'], true)
            ? $this->getSkuMap($run->id)
            : null;

        foreach ($rows as $row) {
            $result = match ($run->type) {
                'price' => $this->applyPriceRow($run, $row, $skuMap),
                'stock' => $this->applyStockRow($run, $row, $skuMap),
                'products' => $this->applyProductRow($run, $row),
                'content' => $this->applyContentRow($run, $row),
                default => ['status' => 'failed', 'sku' => '', 'reason' => 'Unknown import type'],
            };

            match ($result['status']) {
                'updated' => $updated++,
                'created' => $created++,
                'skipped' => $skipped++,
                default => $failed++,
            };

            if (in_array($result['status'], ['skipped', 'failed'], true)) {
                $reportLines[] = [
                    'sku' => $result['sku'] ?? '',
                    'status' => $result['status'],
                    'reason' => $result['reason'] ?? '',
                ];
            }
        }

        // One DB write for counters per chunk (not per row)
        DB::table('import_runs')->where('id', $run->id)->update([
            'processed_rows' => DB::raw('processed_rows + '.(int) count($rows)),
            'updated_rows' => DB::raw('updated_rows + '.$updated),
            'created_rows' => DB::raw('created_rows + '.$created),
            'skipped_rows' => DB::raw('skipped_rows + '.$skipped),
            'failed_rows' => DB::raw('failed_rows + '.$failed),
            'updated_at' => now(),
        ]);

        if ($reportLines) {
            $this->appendReport($run, $reportLines);
        }

        $run->refresh();
        if ($run->processed_rows >= $run->total_rows && $run->total_rows > 0) {
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
            Cache::forget($this->skuMapCacheKey($run->id));
        }
    }

    protected function rowsToJsonlPath(ImportRun $run, array $rows): string
    {
        $relative = 'imports/jsonl/run_'.$run->id.'.jsonl';
        $full = Storage::disk('local')->path($relative);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }

        $fh = fopen($full, 'w');
        foreach ($rows as $row) {
            fwrite($fh, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
        }
        fclose($fh);

        return $relative;
    }

    protected function readJsonlSlice(string $relativePath, int $offset, int $limit): array
    {
        $full = Storage::disk('local')->path($relativePath);
        $rows = [];
        $fh = fopen($full, 'r');
        $i = 0;
        while (($line = fgets($fh)) !== false) {
            if ($i < $offset) {
                $i++;
                continue;
            }
            if ($i >= $offset + $limit) {
                break;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
            $i++;
        }
        fclose($fh);

        return $rows;
    }

    protected function skuMapCacheKey(int $runId): string
    {
        return 'import_sku_map_'.$runId;
    }

    protected function warmSkuMap(int $runId): void
    {
        $map = ProductVariant::query()->pluck('id', 'sku')->all();
        Cache::put($this->skuMapCacheKey($runId), $map, now()->addHours(6));
    }

    protected function getSkuMap(int $runId): array
    {
        $key = $this->skuMapCacheKey($runId);
        if (! Cache::has($key)) {
            $this->warmSkuMap($runId);
        }

        return Cache::get($key, []);
    }

    protected function applyPriceRow(ImportRun $run, array $row, ?array $skuMap = null): array
    {
        $sku = SpreadsheetReader::normalizeSku($row['sku'] ?? '');
        if ($sku === '') {
            return ['status' => 'skipped', 'sku' => '', 'reason' => 'Empty SKU'];
        }

        $priceRaw = $row['price'] ?? '';
        if ($priceRaw === '') {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'Empty price (skip)'];
        }

        $price = round((float) str_replace([',', '$'], '', $priceRaw), 2);

        $variant = null;
        if (is_array($skuMap)) {
            $id = $skuMap[$sku] ?? null;
            if (! $id) {
                return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'SKU not found'];
            }
            $variant = ProductVariant::find($id);
        } else {
            $variant = ProductVariant::where('sku', $sku)->first();
        }

        if (! $variant) {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'SKU not found'];
        }

        if (abs((float) $variant->price - $price) < 0.0001) {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'Price unchanged'];
        }

        if (! $run->dry_run) {
            $data = ['price' => $price];
            if (($row['sale_price'] ?? '') !== '') {
                $data['sale_price'] = round((float) str_replace([',', '$'], '', $row['sale_price']), 2);
            }
            if (($row['cost'] ?? '') !== '') {
                $data['cost'] = round((float) str_replace([',', '$'], '', $row['cost']), 2);
            } elseif (($row['dist_net_price'] ?? '') !== '') {
                $data['cost'] = round((float) str_replace([',', '$'], '', $row['dist_net_price']), 2);
            }
            $variant->update($data);
        }

        return ['status' => 'updated', 'sku' => $sku];
    }

    protected function applyStockRow(ImportRun $run, array $row, ?array $skuMap = null): array
    {
        $sku = SpreadsheetReader::normalizeSku($row['sku'] ?? '');
        if ($sku === '') {
            return ['status' => 'skipped', 'sku' => '', 'reason' => 'Empty SKU'];
        }

        $variant = null;
        if (is_array($skuMap)) {
            $id = $skuMap[$sku] ?? null;
            if (! $id) {
                return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'SKU not found'];
            }
            $variant = ProductVariant::find($id);
        } else {
            $variant = ProductVariant::where('sku', $sku)->first();
        }

        if (! $variant) {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'SKU not found'];
        }

        $hasQty = ($row['qty'] ?? '') !== '';
        $hasStatus = ($row['stock_status'] ?? '') !== '';

        if (! $hasQty && ! $hasStatus) {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'Empty stock fields (skip)'];
        }

        if (! $run->dry_run) {
            $data = ['stock_source' => 'csv', 'stock_updated_at' => now()];
            if ($hasQty) {
                $data['qty'] = (int) $row['qty'];
                $data['stock_status'] = ((int) $row['qty'] > 0) ? 'instock' : 'outofstock';
            }
            if ($hasStatus) {
                $status = strtolower(trim($row['stock_status']));
                $status = in_array($status, ['1', 'in', 'instock', 'in stock', 'yes'], true) ? 'instock' : (
                    in_array($status, ['0', 'out', 'outofstock', 'out of stock', 'no'], true) ? 'outofstock' : $status
                );
                $data['stock_status'] = $status;
                if (! $hasQty) {
                    $data['qty'] = $status === 'instock' ? max(1, (int) $variant->qty) : 0;
                }
            }
            $variant->update($data);
        }

        return ['status' => 'updated', 'sku' => $sku];
    }

    protected function applyProductRow(ImportRun $run, array $row): array
    {
        $sku = SpreadsheetReader::normalizeSku($row['sku'] ?? '');
        if ($sku === '') {
            return ['status' => 'skipped', 'sku' => '', 'reason' => 'Empty SKU'];
        }

        $variant = ProductVariant::where('sku', $sku)->first();
        $exists = (bool) $variant;

        if ($exists && $run->mode === 'create_skip_existing') {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'Already exists'];
        }

        if (! $exists && $run->mode === 'update') {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'SKU not found (update-only)'];
        }

        if (! $exists && ($row['title'] ?? '') === '') {
            return ['status' => 'failed', 'sku' => $sku, 'reason' => 'Title required to create'];
        }

        if ($run->dry_run) {
            return ['status' => $exists ? 'updated' : 'created', 'sku' => $sku];
        }

        if ($exists) {
            $product = $variant->product;
            $this->fillProductFromRow($product, $row, skipEmpty: true);
            $this->fillVariantFromRow($variant, $row, skipEmpty: true);
            $this->syncRelationsFromRow($product, $row);
            $product->save();
            $variant->save();

            return ['status' => 'updated', 'sku' => $sku];
        }

        $slug = ($row['slug'] ?? '') !== '' ? Str::slug($row['slug']) : Str::slug(($row['title'] ?? $sku).'-'.$sku);
        $product = Product::create([
            'name' => $row['title'],
            'slug' => $this->uniqueSlug($slug),
            'part_number' => $row['part_number'] ?? $sku,
            'description' => $row['description'] ?? null,
            'short_description' => $row['short_description'] ?? null,
            'meta_title' => $row['meta_title'] ?? null,
            'meta_description' => $row['meta_description'] ?? null,
            'focus_keyphrase' => $row['focus_keyphrase'] ?? null,
            'meta_keywords' => $row['meta_keywords'] ?? null,
            'status' => 'published',
            'brand_id' => $this->resolveBrandId($row['brand'] ?? null),
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'price' => ($row['price'] ?? '') !== '' ? (float) $row['price'] : 0,
            'sale_price' => ($row['sale_price'] ?? '') !== '' ? (float) $row['sale_price'] : null,
            'cost' => ($row['cost'] ?? '') !== '' ? (float) $row['cost'] : null,
            'qty' => ($row['qty'] ?? '') !== '' ? (int) $row['qty'] : 0,
            'weight' => ($row['weight'] ?? '') !== '' ? (float) $row['weight'] : null,
            'length' => ($row['length'] ?? '') !== '' ? (float) $row['length'] : null,
            'width' => ($row['width'] ?? '') !== '' ? (float) $row['width'] : null,
            'height' => ($row['height'] ?? '') !== '' ? (float) $row['height'] : null,
            'stock_status' => (($row['qty'] ?? 0) > 0) ? 'instock' : 'outofstock',
            'stock_source' => 'csv',
            'is_default' => true,
        ]);

        $this->syncRelationsFromRow($product, $row);

        return ['status' => 'created', 'sku' => $sku];
    }

    protected function applyContentRow(ImportRun $run, array $row): array
    {
        $sku = SpreadsheetReader::normalizeSku($row['sku'] ?? '');
        if ($sku === '') {
            return ['status' => 'skipped', 'sku' => '', 'reason' => 'Empty SKU'];
        }

        $variant = ProductVariant::where('sku', $sku)->first();
        if (! $variant) {
            return ['status' => 'skipped', 'sku' => $sku, 'reason' => 'SKU not found'];
        }

        if ($run->dry_run) {
            return ['status' => 'updated', 'sku' => $sku];
        }

        $product = $variant->product;
        $this->fillProductFromRow($product, $row, skipEmpty: true);
        $this->syncRelationsFromRow($product, $row);
        $product->save();

        return ['status' => 'updated', 'sku' => $sku];
    }

    protected function fillProductFromRow(Product $product, array $row, bool $skipEmpty): void
    {
        $map = [
            'title' => 'name',
            'slug' => 'slug',
            'description' => 'description',
            'short_description' => 'short_description',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
            'focus_keyphrase' => 'focus_keyphrase',
            'meta_keywords' => 'meta_keywords',
            'part_number' => 'part_number',
        ];

        foreach ($map as $from => $to) {
            if (! array_key_exists($from, $row)) {
                continue;
            }
            if ($skipEmpty && trim((string) $row[$from]) === '') {
                continue;
            }
            $value = $row[$from];
            if ($to === 'slug') {
                $value = Str::slug($value);
            }
            $product->{$to} = $value;
        }

        if (($row['brand'] ?? '') !== '') {
            $product->brand_id = $this->resolveBrandId($row['brand']);
        }
    }

    protected function fillVariantFromRow(ProductVariant $variant, array $row, bool $skipEmpty): void
    {
        $fields = ['price', 'sale_price', 'cost', 'qty', 'weight', 'length', 'width', 'height', 'stock_status'];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }
            if ($skipEmpty && trim((string) $row[$field]) === '') {
                continue;
            }
            $variant->{$field} = in_array($field, ['qty'], true) ? (int) $row[$field] : (
                $field === 'stock_status' ? $row[$field] : (float) $row[$field]
            );
        }
        if (($row['qty'] ?? '') !== '' || ($row['stock_status'] ?? '') !== '') {
            $variant->stock_source = 'csv';
            $variant->stock_updated_at = now();
        }
    }

    protected function syncRelationsFromRow(Product $product, array $row): void
    {
        if (($row['categories'] ?? '') !== '') {
            $names = preg_split('/[|,]/', $row['categories']) ?: [];
            $ids = [];
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $cat = Category::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'is_active' => true]
                );
                $ids[] = $cat->id;
            }
            if ($ids) {
                $product->categories()->syncWithoutDetaching($ids);
            }
        }

        // 3-level category path from WP plugin style
        $path = array_filter([
            $row['main_category'] ?? null,
            $row['sub_category'] ?? null,
            $row['sub_sub_category'] ?? null,
        ]);
        if ($path) {
            $parentId = null;
            $leafId = null;
            foreach ($path as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $cat = Category::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'parent_id' => $parentId, 'is_active' => true]
                );
                if ($cat->parent_id === null && $parentId) {
                    $cat->update(['parent_id' => $parentId]);
                }
                $parentId = $cat->id;
                $leafId = $cat->id;
            }
            if ($leafId) {
                $product->categories()->syncWithoutDetaching([$leafId]);
            }
        }

        if (($row['tags'] ?? '') !== '') {
            $names = preg_split('/[|,]/', $row['tags']) ?: [];
            $ids = [];
            foreach ($names as $name) {
                $name = trim($name);
                if ($name === '') {
                    continue;
                }
                $tag = Tag::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                );
                $ids[] = $tag->id;
            }
            if ($ids) {
                $product->tags()->syncWithoutDetaching($ids);
            }
        }

        if (($row['cross_reference'] ?? '') !== '') {
            $refs = preg_split('/[|,;]/', $row['cross_reference']) ?: [];
            foreach ($refs as $ref) {
                $ref = trim($ref);
                if ($ref === '') {
                    continue;
                }
                CrossReference::firstOrCreate(
                    ['product_id' => $product->id, 'ref_number' => $ref],
                    ['ref_type' => 'oem']
                );
            }
        }
    }

    protected function resolveBrandId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Brand::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true]
        )->id;
    }

    protected function uniqueSlug(string $slug): string
    {
        $base = $slug;
        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    protected function appendReport(ImportRun $run, array $lines): void
    {
        $path = $run->report_path ?: 'imports/reports/run_'.$run->id.'.csv';
        $full = Storage::disk('local')->path($path);
        if (! is_dir(dirname($full))) {
            mkdir(dirname($full), 0755, true);
        }
        $writeHeader = ! file_exists($full);
        $fh = fopen($full, 'a');
        if ($writeHeader) {
            fputcsv($fh, ['sku', 'status', 'reason']);
        }
        foreach ($lines as $line) {
            fputcsv($fh, [$line['sku'], $line['status'], $line['reason']]);
        }
        fclose($fh);

        if (! $run->report_path) {
            $run->update(['report_path' => $path]);
        }
    }
}
