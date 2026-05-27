<?php

namespace App\Modules\Lodging\Filament;

use App\Modules\Lodging\Filament\BuildingResource\Pages;
use App\Modules\Lodging\Models\Building;
use Filament\Facades\Filament;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Pennant\Feature;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return Feature::active('lodging', Filament::getTenant());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),

                Components\TextInput::make('address')
                    ->required()
                    ->maxLength(255),

                Components\Repeater::make('rooms')
                    ->relationship('rooms')
                    ->schema([
                        Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Components\TextInput::make('capacity')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Components\Select::make('room_type')
                            ->options([
                                'single' => 'Single',
                                'double' => 'Double',
                                'dorm' => 'Dormitory',
                                'suite' => 'Suite',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rooms_count')
                    ->label('Rooms')
                    ->counts('rooms'),

                Tables\Columns\TextColumn::make('total_capacity')
                    ->label('Total Capacity')
                    ->getStateUsing(fn (Building $record): int => $record->total_capacity),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkDeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuildings::route('/'),
            'create' => Pages\CreateBuilding::route('/create'),
            'edit' => Pages\EditBuilding::route('/{record}/edit'),
        ];
    }
}
