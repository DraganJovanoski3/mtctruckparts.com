<?php

namespace App\Filament\Pages;

use App\Services\AutomannMarkupService;
use App\Services\BulkImportService;
use App\Support\SpreadsheetReader;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AutomannMarkupCalculator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Bulk tools';

    protected static ?string $navigationLabel = 'Automann price update';

    protected static ?string $title = 'Automann price update (markup → catalog)';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.automann-markup';

    public ?array $data = [];

    public array $lastStats = [];

    public ?int $lastImportRunId = null;

    public function mount(): void
    {
        $this->form->fill([
            'apply_to_catalog' => true,
            'also_set_cost' => true,
            'download_csv' => true,
            'dry_run' => false,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('Automann price file (CSV / XLSX / XML)')
                    ->helperText('Same as your WP price-update + Python markup tool. Needs Dist. Price List (cost) + Automann Part Number / SKU. Excel 2003 XML exports are supported.')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'text/xml',
                        'application/xml',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/xml',
                    ])
                    ->disk('local')
                    ->directory('imports/markup')
                    ->maxSize(102400)
                    ->required(),
                Forms\Components\Toggle::make('apply_to_catalog')
                    ->label('Update catalog prices (queued like WooCommerce price XLSX plugin)')
                    ->helperText('Applies marked-up Updated Price to matching SKUs via Import history / queue:work')
                    ->default(true)
                    ->live(),
                Forms\Components\Toggle::make('also_set_cost')
                    ->label('Save original Dist. Price List into cost')
                    ->visible(fn (Forms\Get $get) => (bool) $get('apply_to_catalog'))
                    ->default(true),
                Forms\Components\Toggle::make('dry_run')
                    ->label('Dry run (report only, no price writes)')
                    ->visible(fn (Forms\Get $get) => (bool) $get('apply_to_catalog')),
                Forms\Components\Toggle::make('download_csv')
                    ->label('Download marked-up CSV after run')
                    ->default(true),
            ])
            ->statePath('data');
    }

    public function run(AutomannMarkupService $markup, BulkImportService $imports)
    {
        $state = $this->form->getState();
        $file = $state['file'] ?? null;
        if (is_array($file)) {
            $file = reset($file);
        }
        if (! $file) {
            Notification::make()->title('Upload a file')->danger()->send();

            return null;
        }

        $path = is_string($file) ? Storage::disk('local')->path($file) : $file->getRealPath();
        $originalName = is_string($file) ? basename($file) : ($file->getClientOriginalName() ?? 'automann.csv');

        try {
            $data = SpreadsheetReader::read($path);
        } catch (\Throwable $e) {
            Notification::make()->title('Could not read file')->body($e->getMessage())->danger()->send();

            return null;
        }

        if ($data['rows'] === []) {
            Notification::make()->title('No data rows found')->danger()->send();

            return null;
        }

        $result = $markup->transformRows($data['rows']);
        $this->lastStats = $result['stats'];
        $this->lastImportRunId = null;

        // Build import-ready rows: sku + price (+ cost)
        $priceRows = [];
        foreach ($result['rows'] as $row) {
            $sku = SpreadsheetReader::normalizeSku($row['sku'] ?? '');
            $price = $row['updated_price'] ?? $row['price'] ?? '';
            if ($sku === '' || $price === '') {
                continue;
            }
            $out = [
                'sku' => $sku,
                'price' => $price,
                'updated_price' => $price,
            ];
            if (! empty($state['also_set_cost']) && ($row['original_cost'] ?? $row['cost'] ?? '') !== '') {
                $out['cost'] = $row['original_cost'] ?? $row['cost'];
            }
            $priceRows[] = $out;
        }

        $stamp = now()->format('Ymd_His');
        $outRelative = 'imports/markup/automann-marked-up-'.$stamp.'.csv';
        $outFull = Storage::disk('local')->path($outRelative);
        if (! is_dir(dirname($outFull))) {
            mkdir(dirname($outFull), 0755, true);
        }

        $headers = array_values(array_unique(array_merge(
            $data['headers'] ?: ['sku', 'dist_price_list', 'dist_net_price'],
            ['original_cost', 'markup_multiplier', 'updated_price', 'price', 'cost']
        )));

        $fh = fopen($outFull, 'w');
        fputcsv($fh, $headers);
        foreach ($result['rows'] as $row) {
            $row['price'] = $row['updated_price'] ?? ($row['price'] ?? '');
            if (($row['cost'] ?? '') === '' && ($row['original_cost'] ?? '') !== '') {
                $row['cost'] = $row['original_cost'];
            }
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($fh, $line);
        }
        fclose($fh);

        $catalogMsg = '';
        if (! empty($state['apply_to_catalog'])) {
            if ($priceRows === []) {
                Notification::make()
                    ->title('Markup done, but nothing to apply')
                    ->body('No rows had both SKU and Updated Price.')
                    ->warning()
                    ->send();
            } else {
                // Write a lean price sheet for the queue importer (same path as WP XLSX price plugin)
                $importRelative = 'imports/markup/automann-price-apply-'.$stamp.'_'.Str::random(6).'.csv';
                $importFull = Storage::disk('local')->path($importRelative);
                $pf = fopen($importFull, 'w');
                fputcsv($pf, ['sku', 'price', 'cost']);
                foreach ($priceRows as $pr) {
                    fputcsv($pf, [$pr['sku'], $pr['price'], $pr['cost'] ?? '']);
                }
                fclose($pf);

                $run = $imports->startFromUpload(
                    'price',
                    $importFull,
                    'automann-'.$originalName,
                    'update',
                    (bool) ($state['dry_run'] ?? false)
                );
                $this->lastImportRunId = $run->id;
                $catalogMsg = " Queued import run #{$run->id} ({$run->status}) for ".count($priceRows).' SKUs. Watch Bulk tools → Import history. Run `php artisan queue:work` if needed.';
            }
        }

        Notification::make()
            ->title('Automann markup complete')
            ->body("Processed {$this->lastStats['processed']}, marked up {$this->lastStats['updated']}, skipped empty {$this->lastStats['skipped_empty']}.{$catalogMsg}")
            ->success()
            ->persistent()
            ->send();

        if (! empty($state['download_csv'])) {
            return Storage::disk('local')->download($outRelative, 'automann-marked-up.csv');
        }

        return null;
    }

    public function getTiersProperty(): array
    {
        return app(AutomannMarkupService::class)->tiersExplanation();
    }
}
