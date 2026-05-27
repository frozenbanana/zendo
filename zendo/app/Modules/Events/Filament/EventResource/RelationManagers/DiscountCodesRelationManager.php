<?php

namespace App\Modules\Events\Filament\EventResource\RelationManagers;

use Filament\Forms\Components;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DiscountCodesRelationManager extends RelationManager
{
    protected static string $relationship = 'discountCodes';

    protected static ?string $title = 'Discount Codes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-ticket';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),

                Components\TextInput::make('description')
                    ->maxLength(255),

                Components\Select::make('type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ])
                    ->required()
                    ->default('percentage'),

                Components\TextInput::make('value')
                    ->required()
                    ->numeric()
                    ->minValue(0),

                Components\DateTimePicker::make('starts_at'),

                Components\DateTimePicker::make('expires_at'),

                Components\TextInput::make('max_redemptions')
                    ->numeric()
                    ->minValue(1),

                Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge(),

                Tables\Columns\TextColumn::make('value')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('times_used')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkDeleteAction::make(),
            ]);
    }
}
