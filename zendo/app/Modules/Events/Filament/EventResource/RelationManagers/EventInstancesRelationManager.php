<?php

namespace App\Modules\Events\Filament\EventResource\RelationManagers;

use Filament\Forms\Components;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EventInstancesRelationManager extends RelationManager
{
    protected static string $relationship = 'eventInstances';

    protected static ?string $title = 'Instances';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-calendar-days';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Components\DateTimePicker::make('starts_at')
                    ->required(),

                Components\DateTimePicker::make('ends_at')
                    ->required()
                    ->afterOrEqual('starts_at'),

                Components\TextInput::make('capacity')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leave empty to use the event default capacity.'),

                Components\TextInput::make('price_override_cents')
                    ->numeric()
                    ->label('Price override (cents)')
                    ->helperText('Leave empty to use the event base price.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity')
                    ->numeric()
                    ->sortable()
                    ->default('Inherited'),

                Tables\Columns\TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations'),
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
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
