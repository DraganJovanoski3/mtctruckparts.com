<?php

namespace App\Filament\Pages;

use App\Services\CatalogExportService;
use App\Services\ImageBySkuService;
use App\Support\SpreadsheetReader;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ImageBySkuUpload extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Bulk tools';

    protected static ?string $navigationLabel = 'Images by SKU';

    protected static ?string $title = 'Product images by SKU';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.image-by-sku';

    public ?array $filesData = [];

    public ?array $urlsData = [];

    public ?array $exportData = [];

    public array $lastSkipped = [];

    public function mount(): void
    {
        $this->filesForm->fill(['replace' => true]);
        $this->urlsForm->fill(['replace' => true]);
        $this->exportForm->fill([]);
    }

    protected function getForms(): array
    {
        return ['filesForm', 'urlsForm', 'exportForm'];
    }

    public function filesForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('images')
                ->label('Image files (filename = SKU)')
                ->helperText('Example: SKF-1643-C30.jpg or 1643-C30.png — matched to product SKU')
                ->multiple()
                ->image()
                ->disk('local')
                ->directory('imports/images-tmp')
                ->maxFiles(200)
                ->required(),
            Forms\Components\Toggle::make('replace')
                ->label('Replace primary image if product already has one')
                ->default(true),
        ])->statePath('filesData');
    }

    public function urlsForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('sheet')
                ->label('XLSX/CSV with sku + image_url')
                ->acceptedFileTypes([
                    'text/csv',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->disk('local')
                ->directory('imports/images-tmp')
                ->required(),
            Forms\Components\Toggle::make('replace')
                ->label('Replace primary image')
                ->default(true),
        ])->statePath('urlsData');
    }

    public function exportForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('brand')
                ->label('Brand filter (optional)')
                ->options(fn () => app(CatalogExportService::class)->brandsForFilter())
                ->searchable()
                ->placeholder('All brands'),
        ])->statePath('exportData');
    }

    public function matchFilenames(ImageBySkuService $images): void
    {
        $state = $this->filesForm->getState();
        $files = $state['images'] ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }
        if (! $files) {
            Notification::make()->title('Upload images')->danger()->send();

            return;
        }

        $paths = [];
        foreach ($files as $file) {
            $paths[] = is_string($file) ? Storage::disk('local')->path($file) : $file;
        }

        $result = $images->attachByFilenames($paths, (bool) ($state['replace'] ?? true));
        $this->lastSkipped = $result['skipped'];

        Notification::make()
            ->title("Attached {$result['attached']} images")
            ->body(count($result['skipped']).' skipped — see list below')
            ->success()
            ->send();
    }

    public function importUrls(ImageBySkuService $images): void
    {
        $state = $this->urlsForm->getState();
        $file = $state['sheet'] ?? null;
        if (is_array($file)) {
            $file = reset($file);
        }
        if (! $file) {
            Notification::make()->title('Upload a sheet')->danger()->send();

            return;
        }

        $path = is_string($file) ? Storage::disk('local')->path($file) : $file->getRealPath();
        $rows = SpreadsheetReader::read($path)['rows'];
        $result = $images->attachFromSheetRows($rows, (bool) ($state['replace'] ?? true));
        $this->lastSkipped = array_map(fn ($s) => [
            'file' => $s['sku'],
            'reason' => $s['reason'],
        ], $result['skipped']);

        Notification::make()
            ->title("Imported {$result['attached']} images from URLs")
            ->body(count($result['skipped']).' skipped')
            ->success()
            ->send();
    }

    public function exportMissing(ImageBySkuService $images)
    {
        $brand = $this->exportForm->getState()['brand'] ?? null;

        return $images->exportMissingImages($brand ?: null);
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['sku', 'image_url']);
            fputcsv($out, ['SKF-1643-C30', 'https://example.com/images/1643-C30.jpg']);
            fclose($out);
        }, 'template-images-by-sku.csv', ['Content-Type' => 'text/csv']);
    }
}
