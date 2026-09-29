<?php

namespace App\Services;

use App\Jobs\ApplyPriceRuleChunkJob;
use App\Models\PriceRule;
use App\Models\PriceRuleRun;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PriceRuleService
{
    public const CHUNK_SIZE = 2000;

    public function variantQuery(PriceRule $rule): Builder
    {
        $query = ProductVariant::query()->whereHas('product', function (Builder $q) {
            $q->where('status', 'published');
        });

        return match ($rule->scope_type) {
            'brand' => $query->whereHas('product', fn (Builder $q) => $q->whereIn('brand_id', $rule->scope_ids ?? [])),
            'category' => $query->whereHas('product.categories', fn (Builder $q) => $q->whereIn('categories.id', $rule->scope_ids ?? [])),
            'tag' => $query->whereHas('product.tags', fn (Builder $q) => $q->whereIn('tags.id', $rule->scope_ids ?? [])),
            default => $query,
        };
    }

    public function preview(PriceRule $rule, int $limit = 10): array
    {
        $query = $this->variantQuery($rule);
        $count = (clone $query)->count();
        $samples = (clone $query)->with('product')->limit($limit)->get()->map(function (ProductVariant $variant) use ($rule) {
            return [
                'sku' => $variant->sku,
                'product' => $variant->product?->name,
                'old_price' => (float) $variant->price,
                'new_price' => $this->calculateNewPrice($variant, $rule),
            ];
        });

        return [
            'affected_count' => $count,
            'samples' => $samples,
        ];
    }

    public function calculateNewPrice(ProductVariant $variant, PriceRule $rule): float
    {
        $base = $rule->formula_type === 'percent_of_cost' && $variant->cost !== null
            ? (float) $variant->cost
            : (float) $variant->price;

        $new = $base * (1 + ((float) $rule->percent / 100));

        if ($rule->round_to === '.99') {
            $new = floor($new) + 0.99;
        } elseif ($rule->round_to === '.00') {
            $new = round($new);
        } else {
            $new = round($new, 2);
        }

        return max(0, $new);
    }

    public function dispatch(PriceRule $rule): PriceRuleRun
    {
        $ids = $this->variantQuery($rule)->orderBy('id')->pluck('id');
        $chunks = $ids->chunk(self::CHUNK_SIZE)->values();

        $run = PriceRuleRun::create([
            'price_rule_id' => $rule->id,
            'user_id' => Auth::id(),
            'status' => 'queued',
            'total_chunks' => $chunks->count(),
            'completed_chunks' => 0,
            'affected_rows' => 0,
            'started_at' => now(),
        ]);

        foreach ($chunks as $index => $chunkIds) {
            ApplyPriceRuleChunkJob::dispatch($run->id, $chunkIds->values()->all(), $index);
        }

        if ($chunks->isEmpty()) {
            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }

        return $run;
    }
}
