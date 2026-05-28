<?php

namespace App\Modules\Events\Filament;

use App\Modules\Events\Enums\EventStatus;
use App\Modules\Events\Filament\EventResource\Pages;
use App\Modules\Events\Filament\EventResource\RelationManagers;
use App\Modules\Events\Models\Event;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Event Details')
                    ->schema([
                        Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        Components\Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Components\Select::make('status')
                            ->options(array_column(EventStatus::cases(), 'value', 'value'))
                            ->required()
                            ->default(EventStatus::Draft->value),

                        Components\DateTimePicker::make('starts_at')
                            ->required(),

                        Components\DateTimePicker::make('ends_at')
                            ->required()
                            ->afterOrEqual('starts_at'),

                        Components\TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1),

                        Components\TextInput::make('price_cents')
                            ->numeric()
                            ->label('Price (cents)')
                            ->helperText('Base price in cents. E.g., 50000 = $500'),
                    ]),

                Components\Section::make('Teachers & Categories')
                    ->schema([
                        Components\Select::make('teachers')
                            ->relationship('teachers', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),

                        Components\Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EventStatus::Draft->value => 'gray',
                        EventStatus::Published->value => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_column(EventStatus::cases(), 'value', 'value')),

                TernaryFilter::make('has_capacity')
                    ->label('Has capacity')
                    ->placeholder('All events')
                    ->trueLabel('Has capacity')
                    ->falseLabel('Full')
                    ->queries(
                        true: fn ($query) => $query->whereColumn('registrations_count', '<', 'capacity'),
                        false: fn ($query) => $query->whereColumn('registrations_count', '>=', 'capacity'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkDeleteAction::make(),
            ])
            ->defaultSort('starts_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EventInstancesRelationManager::class,
            RelationManagers\DiscountCodesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'view' => Pages\ViewEvent::route('/{record}'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
