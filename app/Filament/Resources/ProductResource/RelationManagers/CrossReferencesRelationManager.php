<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CrossReferencesRelationManager extends RelationManager
{
    protected static string $relationship = 'crossReferences';

    protected static ?string $title = 'Cross references';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('ref_number')->required(),
            Forms\Components\Select::make('ref_type')->options([
                'oem' => 'OEM',
                'competitor' => 'Competitor',
                'internal' => 'Internal',
            ])->default('oem')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ref_number')->searchable(),
                Tables\Columns\TextColumn::make('ref_type')->badge(),
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
