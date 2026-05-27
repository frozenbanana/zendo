<?php

namespace App\Modules\Memberships\Filament\MembershipPlanResource\Pages;

use App\Modules\Memberships\Filament\MembershipPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMembershipPlan extends EditRecord
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
