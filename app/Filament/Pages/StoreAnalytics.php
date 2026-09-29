<?php

namespace App\Filament\Pages;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class StoreAnalytics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Store analytics';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.store-analytics';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'preset' => '30',
            'from' => now()->subDays(29)->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('preset')
                    ->label('Range')
                    ->options([
                        '7' => 'Last 7 days',
                        '30' => 'Last 30 days',
                        '90' => 'Last 90 days',
                        'ytd' => 'Year to date',
                        'all' => 'All time',
                        'custom' => 'Custom dates',
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state === 'custom' || $state === null) {
                            return;
                        }
                        if ($state === 'all') {
                            $set('from', null);
                            $set('to', null);

                            return;
                        }
                        if ($state === 'ytd') {
                            $set('from', now()->startOfYear()->toDateString());
                            $set('to', now()->toDateString());

                            return;
                        }
                        $days = (int) $state;
                        $set('from', now()->subDays($days - 1)->toDateString());
                        $set('to', now()->toDateString());
                    }),
                Forms\Components\DatePicker::make('from')
                    ->label('From')
                    ->visible(fn (Forms\Get $get) => $get('preset') === 'custom'),
                Forms\Components\DatePicker::make('to')
                    ->label('To')
                    ->visible(fn (Forms\Get $get) => $get('preset') === 'custom'),
            ])
            ->columns(3)
            ->statePath('data');
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatsProperty(): array
    {
        $state = $this->form->getState();
        $preset = $state['preset'] ?? '30';

        $from = null;
        $to = null;

        if ($preset === 'all') {
            $from = null;
            $to = null;
        } elseif ($preset === 'custom') {
            $from = ! empty($state['from']) ? Carbon::parse($state['from']) : null;
            $to = ! empty($state['to']) ? Carbon::parse($state['to']) : null;
        } elseif ($preset === 'ytd') {
            $from = now()->startOfYear();
            $to = now();
        } else {
            $days = (int) ($preset ?: 30);
            $from = now()->subDays(max($days, 1) - 1)->startOfDay();
            $to = now();
        }

        return app(AnalyticsService::class)->dashboard($from, $to);
    }

    public function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }
}
