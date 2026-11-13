<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed data awal aplikasi CAT.
     */
    public function run(): void
    {
        $unitNames = [
            'Farmasi',
            'Keperawatan',
            'Laboratorium',
            'Radiologi',
            'Rekam Medis',
            'Gizi',
            'Keuangan',
            'SDI',
            'IT',
        ];

        foreach ($unitNames as $name) {
            Unit::updateOrCreate(
                ['name' => $name],
                ['status' => 'active']
            );
        }
    }
}
