<?php

namespace App\Filament\Pages;

use App\Models\ImportRun;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportHistory extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Bulk tools';

    protected static ?string $navigationLabel = 'Import history';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.import-history';

    public function getRunsProperty()
    {
        return ImportRun::latest()->paginate(25);
    }

    public function downloadReport(int $runId): StreamedResponse
    {
        $run = ImportRun::findOrFail($runId);
        abort_unless($run->report_path && Storage::disk('local')->exists($run->report_path), 404);

        return Storage::disk('local')->download($run->report_path, 'import-run-'.$run->id.'-skipped.csv');
    }
}
