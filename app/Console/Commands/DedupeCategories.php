<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeCategories extends Command
{
    protected $signature = 'categories:dedupe';

    protected $description = 'Merge duplicate categories that share the same parent and name';

    public function handle(): int
    {
        $groups = Category::query()
            ->where('is_active', true)
            ->get()
            ->groupBy(fn (Category $c) => ($c->parent_id ?? 'root').'|'.mb_strtolower(trim($c->name)));

        $merged = 0;

        DB::transaction(function () use ($groups, &$merged) {
            foreach ($groups as $group) {
                if ($group->count() < 2) {
                    continue;
                }

                // Keep the one with most children, then most products, then lowest id
                $keeper = $group->sortByDesc(function (Category $c) {
                    return $c->children()->count() * 1000 + $c->products()->count();
                })->first();

                foreach ($group as $dup) {
                    if ($dup->id === $keeper->id) {
                        continue;
                    }

                    // Move children
                    Category::where('parent_id', $dup->id)->update(['parent_id' => $keeper->id]);

                    // Move product pivots
                    $productIds = DB::table('category_product')
                        ->where('category_id', $dup->id)
                        ->pluck('product_id');

                    foreach ($productIds as $productId) {
                        DB::table('category_product')->updateOrInsert(
                            ['category_id' => $keeper->id, 'product_id' => $productId],
                            []
                        );
                    }
                    DB::table('category_product')->where('category_id', $dup->id)->delete();

                    $dup->update(['is_active' => false, 'slug' => $dup->slug.'-merged-'.$dup->id]);
                    $merged++;
                    $this->line("Merged #{$dup->id} \"{$dup->name}\" → #{$keeper->id}");
                }
            }

            // Capitalize "wheel"
            Category::where('name', 'wheel')->update(['name' => 'Wheel']);
        });

        $this->info("Merged {$merged} duplicates. Roots: ".Category::whereNull('parent_id')->where('is_active', true)->count());

        return self::SUCCESS;
    }
}
