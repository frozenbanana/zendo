<?php

namespace App\Modules\Events\Filament\Widgets;

use App\Modules\Registration\Models\Registration;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class RegistrationsThisWeekChart extends ChartWidget
{
    protected ?string $heading = 'Registrations This Week';

    protected static ?int $sort = 1;

    protected function getData(): array
    {
        $tenant = Filament::getTenant();

        $data = Registration::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $labels = [];
        $values = [];

        for ($i = 0; $i < 7; $i++) {
            $date = now()->startOfWeek()->addDays($i)->format('Y-m-d');
            $labels[] = now()->startOfWeek()->addDays($i)->format('D');
            $values[] = $data[$date] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Registrations',
                    'data' => $values,
                    'borderColor' => '#4f46e5',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
