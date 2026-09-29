<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Coupons';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('General')->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->helperText('Customers enter this at checkout. Stored uppercase.'),
                Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('discount_type')
                    ->options([
                        'percent' => 'Percentage discount',
                        'fixed_cart' => 'Fixed cart discount',
                        'fixed_product' => 'Fixed product discount',
                    ])
                    ->required()
                    ->default('percent')
                    ->live(),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText(fn (Forms\Get $get) => $get('discount_type') === 'percent'
                        ? 'Percent off eligible items (e.g. 10 = 10%)'
                        : 'Dollar amount off'),
                Forms\Components\Toggle::make('free_shipping')->inline(false),
                Forms\Components\Toggle::make('individual_use')
                    ->label('Individual use only')
                    ->helperText('Cannot be combined with other coupons (reserved for future multi-coupon support).')
                    ->inline(false),
                Forms\Components\Toggle::make('is_active')->default(true)->inline(false),
                Forms\Components\Toggle::make('is_newsletter_welcome')
                    ->label('Newsletter welcome coupon')
                    ->helperText('Shown in the newsletter popup when customers subscribe.')
                    ->inline(false),
            ])->columns(2),

            Forms\Components\Section::make('Usage restriction')->schema([
                Forms\Components\TextInput::make('minimum_amount')->numeric()->prefix('$')->nullable(),
                Forms\Components\TextInput::make('maximum_amount')
                    ->label('Maximum discount')
                    ->numeric()
                    ->prefix('$')
                    ->nullable()
                    ->helperText('Caps percentage discounts.'),
                Forms\Components\Select::make('product_ids')
                    ->label('Products')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => Product::orderBy('name')->limit(500)->pluck('name', 'id'))
                    ->helperText('Leave empty = all products.'),
                Forms\Components\Select::make('exclude_product_ids')
                    ->label('Exclude products')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => Product::orderBy('name')->limit(500)->pluck('name', 'id')),
                Forms\Components\Select::make('category_ids')
                    ->label('Categories')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => Category::orderBy('name')->pluck('name', 'id')),
                Forms\Components\Select::make('exclude_category_ids')
                    ->label('Exclude categories')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => Category::orderBy('name')->pluck('name', 'id')),
                Forms\Components\TagsInput::make('email_restrictions')
                    ->label('Allowed emails')
                    ->placeholder('Add email')
                    ->helperText('Leave empty = any email.'),
                Forms\Components\Toggle::make('exclude_sale_items')->inline(false),
            ])->columns(2),

            Forms\Components\Section::make('Usage limits')->schema([
                Forms\Components\TextInput::make('usage_limit')
                    ->label('Usage limit (total)')
                    ->numeric()
                    ->nullable()
                    ->helperText('How many times this coupon can be used in total.'),
                Forms\Components\TextInput::make('usage_limit_per_user')
                    ->label('Usage limit per email')
                    ->numeric()
                    ->nullable(),
                Forms\Components\TextInput::make('limit_usage_to_x_items')
                    ->label('Limit to X items')
                    ->numeric()
                    ->nullable()
                    ->visible(fn (Forms\Get $get) => $get('discount_type') === 'fixed_product'),
                Forms\Components\Placeholder::make('used_count_display')
                    ->label('Times used')
                    ->content(fn (?Coupon $record) => $record?->used_count ?? 0),
            ])->columns(2),

            Forms\Components\Section::make('Schedule')->schema([
                Forms\Components\DateTimePicker::make('starts_at')->nullable(),
                Forms\Components\DateTimePicker::make('expires_at')->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('discount_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'percent' => 'Percent',
                        'fixed_cart' => 'Fixed cart',
                        'fixed_product' => 'Fixed product',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, Coupon $record) => $record->discount_type === 'percent'
                        ? rtrim(rtrim(number_format((float) $state, 2), '0'), '.').'%'
                        : '$'.number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('used_count')->label('Used')->sortable(),
                Tables\Columns\TextColumn::make('usage_limit')->label('Limit')->placeholder('∞'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
                Tables\Columns\IconColumn::make('is_newsletter_welcome')->boolean()->label('Newsletter'),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->placeholder('Never')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\TernaryFilter::make('is_newsletter_welcome')->label('Newsletter welcome'),
                Tables\Filters\SelectFilter::make('discount_type')->options([
                    'percent' => 'Percent',
                    'fixed_cart' => 'Fixed cart',
                    'fixed_product' => 'Fixed product',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
