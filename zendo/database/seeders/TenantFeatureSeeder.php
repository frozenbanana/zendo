<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\FeatureFlags;

class TenantFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $ivy = Tenant::where('slug', 'ivy')->firstOrFail();
        $ivy->update([
            'features' => new FeatureFlags([
                'meals' => true,
                'lodging' => true,
                'memberships' => true,
            ]),
        ]);

        $nalanda = Tenant::where('slug', 'nalanda')->firstOrFail();
        $nalanda->update([
            'features' => new FeatureFlags([
                'meals' => false,
                'lodging' => true,
                'memberships' => true,
            ]),
        ]);

        $bodhi = Tenant::where('slug', 'bodhi-tree')->firstOrFail();
        $bodhi->update([
            'features' => new FeatureFlags([
                'meals' => true,
                'lodging' => false,
                'memberships' => false,
            ]),
        ]);
    }
}
