<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FactionSeeder::class,
            HeroClassSeeder::class,
            TierSeeder::class,
            ChallengeTypeSeeder::class,
            HeroSynergySeeder::class,
            GuideSeeder::class,
            SignatureItemCostsSeeder::class,
        ]);
    }
}
