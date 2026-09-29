<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\CartService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Preferred top-bar departments (in order). Everything else goes under More. */
    private const PRIMARY_NAV_SLUGS = [
        'suspension-and-steering',
        'brakes-suspension-and-steering',
        'engine-and-drivetrain',
        'electrical-and-lighting',
        'body-and-cabin',
        'tire-and-wheel',
        'air-springs-shocks',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.store', function ($view) {
            $all = Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->where('name', 'not like', '%>%')
                ->orderBy('name')
                ->with(['childrenRecursive'])
                ->get()
                ->filter(fn (Category $c) => $c->children->isNotEmpty())
                ->values();

            $primary = collect();
            foreach (self::PRIMARY_NAV_SLUGS as $slug) {
                $match = $all->first(fn (Category $c) => $c->slug === $slug);
                if ($match && ! $primary->contains('id', $match->id)) {
                    $primary->push($match);
                }
            }

            // Cap primary bar so it never needs horizontal scroll
            $primary = $primary->take(6)->values();
            $primaryIds = $primary->pluck('id');
            $more = $all->reject(fn (Category $c) => $primaryIds->contains($c->id))->values();

            $cart = app(CartService::class);

            $view->with([
                'navCategories' => $primary,
                'navMoreCategories' => $more,
                'cartCount' => $cart->count(),
                'cartPreviewItems' => $cart->items(),
                'cartPreviewSubtotal' => $cart->subtotal(),
            ]);
        });
    }
}
