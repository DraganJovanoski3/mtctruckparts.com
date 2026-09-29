<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceRuleResource\Pages;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceRule;
use App\Services\PriceRuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PriceRuleResource extends Resource
{
    protected static ?string $model = PriceRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Pricing';

    protected static ?string $navigationLabel = 'Bulk % pricing';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Select::make('scope_type')
                ->options([
                    'all' => 'Entire catalog',
                    'brand' => 'Brand(s)',
                    'category' => 'Category(ies)',
                ])
                ->required()
                ->live(),
            Forms\Components\Select::make('scope_ids')
                ->label('Brands')
                ->multiple()
                ->options(fn () => Brand::orderBy('name')->pluck('name', 'id'))
                ->visible(fn (Forms\Get $get) => $get('scope_type') === 'brand'),
            Forms\Components\Select::make('scope_ids')
                ->label('Categories')
                ->multiple()
                ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
                ->visible(fn (Forms\Get $get) => $get('scope_type') === 'category'),
            Forms\Components\Select::make('formula_type')
                ->options([
                    'percent_of_price' => '% of current price',
                    'percent_of_cost' => '% of cost',
                ])
                ->default('percent_of_price')
                ->required(),
            Forms\Components\TextInput::make('percent')
                ->label('Percent (+/-)')
                ->helperText('Example: 10 = +10%, -5 = -5%')
                ->numeric()
                ->required(),
            Forms\Components\Select::make('round_to')
                ->options([
                    '' => '2 decimals',
                    '.99' => 'x.99',
                    '.00' => 'Whole dollar',
                ])
                ->nullable(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('scope_type')->badge(),
                Tables\Columns\TextColumn::make('percent')->suffix('%'),
                Tables\Columns\TextColumn::make('formula_type'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->action(function (PriceRule $record) {
                        $preview = app(PriceRuleService::class)->preview($record);
                        $sample = collect($preview['samples'])->take(3)->map(fn ($s) => "{$s['sku']}: \${$s['old_price']} → \${$s['new_price']}")->implode(' | ');
                        Notification::make()
                            ->title("Affects {$preview['affected_count']} SKUs")
                            ->body($sample ?: 'No sample rows')
                            ->info()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\Action::make('apply')
                    ->label('Apply (queued)')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Apply bulk price rule?')
                    ->modalDescription('This queues chunked jobs for up to the full catalog (50k+). The browser will not time out.')
                    ->action(function (PriceRule $record) {
                        $run = app(PriceRuleService::class)->dispatch($record);
                        Notification::make()
                            ->title('Price run queued')
                            ->body("Run #{$run->id}: {$run->total_chunks} chunks. Run `php artisan queue:work` if workers are not running.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePriceRules::route('/'),
        ];
    }
}
