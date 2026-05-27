<?php

namespace App\Modules\Meals\Filament;

use App\Modules\Meals\Filament\MealPlanResource\Pages;
use App\Modules\Meals\Models\MealPlan;
use Filament\Facades\Filament;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Laravel\Pennant\Feature;

class MealPlanResource extends Resource
{
    protected static ?string $model = MealPlan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return Feature::active('meals', Filament::getTenant());
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

                Components\TextInput::make('price_cents')
                    ->numeric()
                    ->required()
                    ->label('Price (cents)'),

                Components\Select::make('dietary_tags')
                    ->relationship('dietaryTags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Components\Toggle::make('is_available')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_cents')
                    ->money(fn () => Filament::getTenant()->currency)
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_available')
                    ->boolean(),

                Tables\Columns\TextColumn::make('dietaryTags.name')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_available'),
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
            'index' => Pages\ListMealPlans::route('/'),
            'create' => Pages\CreateMealPlan::route('/create'),
            'edit' => Pages\EditMealPlan::route('/{record}/edit'),
        ];
    }
}
