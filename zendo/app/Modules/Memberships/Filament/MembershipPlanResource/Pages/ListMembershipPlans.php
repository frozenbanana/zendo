<?php

namespace App\Modules\Memberships\Filament\MembershipPlanResource\Pages;

use App\Modules\Memberships\Filament\MembershipPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMembershipPlans extends ListRecords
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
