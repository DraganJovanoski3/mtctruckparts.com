<?php

namespace App\Jobs;

use App\Models\ImportRun;
use App\Services\BulkImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportChunkJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public int $runId,
        public int $offset,
        public int $limit,
        public bool $isLastChunk = false,
    ) {}

    public function handle(BulkImportService $service): void
    {
        $run = ImportRun::find($this->runId);
        if (! $run) {
            return;
        }

        try {
            $service->processChunkByOffset($run, $this->offset, $this->limit, $this->isLastChunk);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }
}
