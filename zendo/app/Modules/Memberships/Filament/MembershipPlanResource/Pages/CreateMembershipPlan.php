<?php

namespace App\Modules\Memberships\Filament\MembershipPlanResource\Pages;

use App\Modules\Memberships\Filament\MembershipPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipPlan extends CreateRecord
{
    protected static string $resource = MembershipPlanResource::class;
}
