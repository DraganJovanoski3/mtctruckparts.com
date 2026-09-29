<?php

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\BulkImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PrepareImportRunJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(public int $runId) {}

    public function handle(BulkImportService $service): void
    {
        $run = ImportRun::find($this->runId);
        if (! $run) {
            return;
        }

        $service->prepareAndDispatch($run);
    }
}
