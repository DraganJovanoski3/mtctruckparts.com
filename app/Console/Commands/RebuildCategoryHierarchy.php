<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RebuildCategoryHierarchy extends Command
{
    protected $signature = 'categories:rebuild-hierarchy {--dry-run : Show changes without writing}';

    protected $description = 'Convert flat "Parent > Child > Leaf" category names into nested parent_id tree';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $paths = Category::query()
            ->where('name', 'like', '%>%')
            ->orderByRaw('LENGTH(name) desc')
            ->get();

        $this->info('Path-style categories: '.$paths->count());

        DB::transaction(function () use ($paths, $dry) {
            foreach ($paths as $category) {
                $segments = collect(preg_split('/\s*>\s*/', $category->name) ?: [])
                    ->map(fn ($s) => trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5)))
                    ->filter()
                    ->values();

                if ($segments->count() < 2) {
                    continue;
                }

                $parentId = null;
                foreach ($segments->slice(0, -1) as $segment) {
                    $parent = $this->findOrCreateSegment($segment, $parentId, $dry);
                    $parentId = $parent->id;
                }

                $leafName = $segments->last();
                $leafSlug = $this->uniqueSlug(Str::slug($leafName), $category->id);

                $this->line("  #{$category->id}: \"{$category->name}\" → parent={$parentId} name=\"{$leafName}\"");

                if (! $dry) {
                    $category->update([
                        'name' => $leafName,
                        'slug' => $leafSlug,
                        'parent_id' => $parentId,
                        'is_active' => true,
                    ]);
                }
            }
        });

        if (! $dry) {
            // Prefer nicer top-level labels from seed categories when duplicates exist
            $this->mergeDuplicateRoots();
        }

        $roots = Category::whereNull('parent_id')->where('is_active', true)->count();
        $this->info($dry ? 'Dry run complete.' : "Done. Active root categories: {$roots}");

        return self::SUCCESS;
    }

    protected function findOrCreateSegment(string $name, ?int $parentId, bool $dry): Category
    {
        $existing = Category::query()
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->where('name', 'not like', '%>%')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Also match by slug under same parent
        $slug = Str::slug($name);
        $bySlug = Category::query()
            ->where('slug', $slug)
            ->where('parent_id', $parentId)
            ->first();

        if ($bySlug && ! str_contains($bySlug->name, '>')) {
            return $bySlug;
        }

        if ($dry) {
            return new Category([
                'id' => 0,
                'name' => $name,
                'slug' => $slug,
                'parent_id' => $parentId,
            ]);
        }

        return Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($slug),
            'parent_id' => $parentId,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    protected function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base ?: 'category';
        $i = 1;
        while (
            Category::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    protected function mergeDuplicateRoots(): void
    {
        // If both "Brake" root and nested Brake exist under a parent, keep nested; leave roots alone.
        // Deactivate empty root leftovers that still contain ">" (shouldn't happen).
        Category::query()
            ->where('name', 'like', '%>%')
            ->update(['is_active' => false]);
    }
}
