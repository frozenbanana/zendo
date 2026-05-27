<?php

namespace Database\Seeders;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['slug' => 'ivy'],
            [
                'name' => 'Ivy Retreat Center',
                'description' => 'A peaceful retreat center nestled in the rolling hills of southern France. Offering yoga, meditation, and holistic wellness programs since 2012.',
                'registration_mode' => 'MANUAL_REVIEW',
                'currency' => 'EUR',
                'timezone' => 'Europe/Paris',
                'locale' => 'en',
                'is_active' => true,
            ]
        );

        Tenant::firstOrCreate(
            ['slug' => 'nalanda'],
            [
                'name' => 'Nalanda Center',
                'description' => 'A modern meditation and philosophy center in the heart of Amsterdam. Focused on mindfulness, Buddhist philosophy, and contemplative arts.',
                'registration_mode' => 'AUTO_CONFIRM',
                'currency' => 'EUR',
                'timezone' => 'Europe/Amsterdam',
                'locale' => 'nl',
                'is_active' => true,
            ]
        );

        Tenant::firstOrCreate(
            ['slug' => 'bodhi-tree'],
            [
                'name' => 'Bodhi Tree House',
                'description' => 'A cozy community space in Thailand offering breathwork, sound healing, and vegetarian cooking retreats. Small groups, deep transformation.',
                'registration_mode' => 'AUTO_IF_PAID',
                'currency' => 'THB',
                'timezone' => 'Asia/Bangkok',
                'locale' => 'en',
                'is_active' => true,
            ]
        );
    }
}
