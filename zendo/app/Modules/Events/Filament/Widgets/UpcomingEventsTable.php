<?php

namespace App\Modules\Events\Filament\Widgets;

use App\Modules\Events\Models\Event;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingEventsTable extends TableWidget
{
    protected static ?string $heading = 'Upcoming Events';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Event::where('tenant_id', Filament::getTenant()->id)
                    ->where('starts_at', '>=', now())
                    ->where('status', 'published')
                    ->orderBy('starts_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('registrations_count')
                    ->label('Registrations')
                    ->counts('registrations'),
            ]);
    }
}
