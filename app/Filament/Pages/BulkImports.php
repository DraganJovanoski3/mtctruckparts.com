<?php

namespace App\Filament\Pages;

use App\Models\ImportRun;
use App\Services\BulkImportService;
use App\Services\CatalogExportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BulkImports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Bulk tools';

    protected static ?string $navigationLabel = 'Bulk uploads';

    protected static ?string $title = 'Bulk uploads (SKU)';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.bulk-imports';

    public ?array $priceData = [];

    public ?array $stockData = [];

    public ?array $productsData = [];

    public ?array $contentData = [];

    public ?array $exportData = [];

    public function mount(): void
    {
        $this->priceForm->fill(['dry_run' => false]);
        $this->stockForm->fill(['dry_run' => false]);
        $this->productsForm->fill([
            'mode' => 'create_or_update',
            'dry_run' => false,
        ]);
        $this->contentForm->fill(['dry_run' => false]);
        $this->exportForm->fill([]);
    }

    protected function getForms(): array
    {
        return [
            'priceForm',
            'stockForm',
            'productsForm',
            'contentForm',
            'exportForm',
        ];
    }

    public function priceForm(Form $form): Form
    {
        return $form
            ->schema($this->uploadSchema('SKU + price (Automann Part Number / Updated Price also work)'))
            ->statePath('priceData');
    }

    public function stockForm(Form $form): Form
    {
        return $form
            ->schema($this->uploadSchema('SKU + qty and/or stock_status (instock/outofstock)'))
            ->statePath('stockData');
    }

    public function productsForm(Form $form): Form
    {
        return $form
            ->schema([
                ...$this->uploadSchema('Full product create/update by SKU (title required for new)'),
                Forms\Components\Select::make('mode')
                    ->options([
                        'create_or_update' => 'Create or update',
                        'update' => 'Update existing only',
                        'create_skip_existing' => 'Create new only (skip existing)',
                    ])
                    ->required(),
            ])
            ->statePath('productsData');
    }

    public function contentForm(Form $form): Form
    {
        return $form
            ->schema($this->uploadSchema('Content/SEO only for existing SKUs (empty cells = skip)'))
            ->statePath('contentData');
    }

    public function exportForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('brand')
                    ->label('Brand (optional)')
                    ->options(fn () => app(CatalogExportService::class)->brandsForFilter())
                    ->searchable()
                    ->placeholder('All brands'),
            ])
            ->statePath('exportData');
    }

    protected function uploadSchema(string $helper): array
    {
        return [
            Forms\Components\FileUpload::make('file')
                ->label('CSV or XLSX (supports 50k+ rows)')
                ->acceptedFileTypes([
                    'text/csv',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->disk('local')
                ->directory('imports/tmp')
                ->maxSize(51200) // 50MB
                ->required()
                ->helperText($helper.' Large files are queued in 1,000-row chunks — browser will not time out.'),
            Forms\Components\Toggle::make('dry_run')
                ->label('Dry run (no DB writes)')
                ->default(false),
        ];
    }

    public function submitPrice(BulkImportService $imports): void
    {
        $this->queueImport('price', $this->priceForm->getState(), $imports, 'update');
    }

    public function submitStock(BulkImportService $imports): void
    {
        $this->queueImport('stock', $this->stockForm->getState(), $imports, 'update');
    }

    public function submitProducts(BulkImportService $imports): void
    {
        $state = $this->productsForm->getState();
        $this->queueImport('products', $state, $imports, $state['mode'] ?? 'create_or_update');
    }

    public function submitContent(BulkImportService $imports): void
    {
        $this->queueImport('content', $this->contentForm->getState(), $imports, 'update');
    }

    protected function queueImport(string $type, array $state, BulkImportService $imports, string $mode): void
    {
        /** @var TemporaryUploadedFile|string|null $file */
        $file = $state['file'] ?? null;
        if (! $file) {
            Notification::make()->title('Upload a file')->danger()->send();

            return;
        }

        if (is_array($file)) {
            $file = reset($file);
        }

        $path = is_string($file)
            ? Storage::disk('local')->path($file)
            : $file->getRealPath();

        $original = is_string($file)
            ? basename($file)
            : $file->getClientOriginalName();

        if (! is_readable($path)) {
            Notification::make()->title('Uploaded file not readable')->danger()->send();

            return;
        }

        $run = $imports->startFromUpload(
            $type,
            $path,
            $original,
            $mode,
            (bool) ($state['dry_run'] ?? false),
        );

        $msg = $run->status === 'completed'
            ? "Run #{$run->id} finished: {$run->updated_rows} updated, {$run->created_rows} created, {$run->skipped_rows} skipped."
            : "Run #{$run->id} accepted for background processing (50k-ready). Start worker: php artisan queue:work --queue=default --tries=3";

        Notification::make()
            ->title($run->status === 'completed' ? 'Import finished' : '50k-scale import queued')
            ->body($msg)
            ->success()
            ->persistent()
            ->send();
    }

    public function downloadExport(CatalogExportService $exports)
    {
        $brand = $this->exportForm->getState()['brand'] ?? null;

        return $exports->exportSkuPriceByBrand($brand ?: null);
    }

    public function downloadTemplate(string $type)
    {
        $templates = [
            'price' => [
                'headers' => ['sku', 'price', 'sale_price', 'cost'],
                'sample' => ['SKF-1643-C30', '13.26', '', '8.50'],
                'name' => 'template-price.csv',
            ],
            'stock' => [
                'headers' => ['sku', 'qty', 'stock_status'],
                'sample' => ['SKF-1643-C30', '25', 'instock'],
                'name' => 'template-stock.csv',
            ],
            'products' => [
                'headers' => [
                    'sku', 'price', 'title', 'brand', 'weight', 'length', 'width', 'height',
                    'slug', 'description', 'short_description', 'focus_keyphrase', 'meta_title',
                    'meta_description', 'meta_keywords', 'tags', 'cross_reference', 'categories',
                    'main_category', 'sub_category', 'qty',
                ],
                'sample' => [
                    'NEW-SKU-001', '49.99', 'Demo Hub Cap', 'SKF', '1.2', '6', '6', '2',
                    'demo-hub-cap', 'Full description here', 'Short desc', 'hub cap', 'Demo Hub Cap | MTC',
                    'Buy demo hub cap', 'hub,cap', 'trailer,hub', '1643,OEM123', 'Wheel',
                    'Tire and Wheel', 'Wheel', '10',
                ],
                'name' => 'template-products.csv',
            ],
            'content' => [
                'headers' => [
                    'sku', 'slug', 'title', 'description', 'short_description', 'focus_keyphrase',
                    'meta_title', 'meta_description', 'meta_keywords', 'tags', 'cross_reference', 'categories',
                ],
                'sample' => [
                    'SKF-1643-C30', '', 'Updated title', 'Updated long description', '', 'hub cap',
                    'Updated SEO title', 'Updated meta', '', 'trailer', '1643', '',
                ],
                'name' => 'template-content-seo.csv',
            ],
            'automann_price' => [
                'headers' => ['Automann Part Number', 'Updated Price'],
                'sample' => ['1643-C30', '13.26'],
                'name' => 'template-automann-price.csv',
            ],
        ];

        $t = $templates[$type] ?? null;
        abort_unless($t, 404);

        return response()->streamDownload(function () use ($t) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $t['headers']);
            fputcsv($out, $t['sample']);
            fclose($out);
        }, $t['name'], ['Content-Type' => 'text/csv']);
    }

    public function getRecentRunsProperty()
    {
        return ImportRun::latest()->limit(15)->get();
    }
}
