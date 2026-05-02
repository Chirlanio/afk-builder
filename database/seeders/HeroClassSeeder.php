<?php

namespace Database\Seeders;

use App\Models\HeroClass;
use Illuminate\Database\Seeder;

class HeroClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['code' => 'tank', 'name' => 'Tank', 'name_pt' => 'Tanque', 'display_order' => 1],
            ['code' => 'warrior', 'name' => 'Warrior', 'name_pt' => 'Guerreiro', 'display_order' => 2],
            ['code' => 'ranger', 'name' => 'Ranger', 'name_pt' => 'Atirador', 'display_order' => 3],
            ['code' => 'mage', 'name' => 'Mage', 'name_pt' => 'Mago', 'display_order' => 4],
            ['code' => 'support', 'name' => 'Support', 'name_pt' => 'Suporte', 'display_order' => 5],
        ];

        foreach ($classes as $data) {
            HeroClass::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
