<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('sku')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('price')->numeric()->required()->prefix('$'),
            Forms\Components\TextInput::make('sale_price')->numeric()->prefix('$'),
            Forms\Components\TextInput::make('cost')->numeric()->prefix('$'),
            Forms\Components\TextInput::make('qty')->numeric()->required()->default(0),
            Forms\Components\Select::make('stock_status')->options([
                'instock' => 'In stock',
                'outofstock' => 'Out of stock',
            ])->default('instock'),
            Forms\Components\TextInput::make('weight')->numeric(),
            Forms\Components\TextInput::make('length')->numeric(),
            Forms\Components\TextInput::make('width')->numeric(),
            Forms\Components\TextInput::make('height')->numeric(),
            Forms\Components\Select::make('stock_source')->options([
                'manual' => 'Manual',
                'csv' => 'CSV',
                'finale' => 'Finale (later)',
            ])->default('manual'),
            Forms\Components\Toggle::make('is_default')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku'),
                Tables\Columns\TextColumn::make('price')->money('USD'),
                Tables\Columns\TextColumn::make('sale_price')->money('USD'),
                Tables\Columns\TextColumn::make('qty'),
                Tables\Columns\TextColumn::make('stock_status')->badge(),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
