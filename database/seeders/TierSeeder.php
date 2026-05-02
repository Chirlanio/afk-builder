<?php

namespace Database\Seeders;

use App\Models\Tier;
use Illuminate\Database\Seeder;

class TierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['code' => 'common', 'name' => 'Common', 'name_pt' => 'Comum', 'max_level' => 100, 'display_order' => 1],
            ['code' => 'legendary', 'name' => 'Legendary', 'name_pt' => 'Lendário', 'max_level' => 160, 'display_order' => 2],
            ['code' => 'ascended', 'name' => 'Ascended', 'name_pt' => 'Ascendido', 'max_level' => 240, 'display_order' => 3],
        ];

        foreach ($tiers as $data) {
            Tier::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
