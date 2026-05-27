<?php

namespace App\Modules\Lodging\Filament\Widgets;

use App\Modules\Lodging\Models\Room;
use App\Modules\Registration\Models\Registration;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Pennant\Feature;

class OccupancyRateStat extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Feature::active('lodging', Filament::getTenant());
    }

    protected function getStats(): array
    {
        $tenant = Filament::getTenant();

        $totalCapacity = Room::whereHas('building', function ($query) use ($tenant) {
            $query->where('tenant_id', $tenant->id);
        })->sum('capacity');

        $occupied = Registration::where('tenant_id', $tenant->id)
            ->where('status', 'confirmed')
            ->count();

        $rate = $totalCapacity > 0 ? round(($occupied / $totalCapacity) * 100, 1) : 0;

        return [
            Stat::make('Occupancy Rate', $rate.'%')
                ->description($occupied.' of '.$totalCapacity.' beds filled')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color($rate > 80 ? 'danger' : ($rate > 50 ? 'warning' : 'success')),
        ];
    }
}
