<?php

namespace App\Jobs;

use App\Models\PriceChangeLog;
use App\Models\PriceRuleRun;
use App\Models\ProductVariant;
use App\Services\PriceRuleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ApplyPriceRuleChunkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $runId,
        public array $variantIds,
        public int $chunkIndex,
    ) {}

    public function handle(PriceRuleService $service): void
    {
        $run = PriceRuleRun::with('rule')->find($this->runId);
        if (! $run || ! $run->rule) {
            return;
        }

        if ($run->status === 'queued') {
            $run->update(['status' => 'processing']);
        }

        $rule = $run->rule;
        $affected = 0;

        DB::transaction(function () use ($service, $rule, $run, &$affected) {
            $variants = ProductVariant::whereIn('id', $this->variantIds)->get();
            $logs = [];

            foreach ($variants as $variant) {
                $old = (float) $variant->price;
                $new = $service->calculateNewPrice($variant, $rule);
                if (abs($old - $new) < 0.0001) {
                    continue;
                }

                $variant->update(['price' => $new]);
                $logs[] = [
                    'price_rule_run_id' => $run->id,
                    'product_variant_id' => $variant->id,
                    'old_price' => $old,
                    'new_price' => $new,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $affected++;
            }

            foreach (array_chunk($logs, 500) as $chunk) {
                PriceChangeLog::insert($chunk);
            }
        });

        $run->refresh();
        $run->increment('completed_chunks');
        $run->increment('affected_rows', $affected);
        $run->refresh();

        if ($run->completed_chunks >= $run->total_chunks) {
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }
    }
}
