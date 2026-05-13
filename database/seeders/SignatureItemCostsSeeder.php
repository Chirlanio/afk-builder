<?php

namespace Database\Seeders;

use App\Models\SignatureItemCost;
use Illuminate\Database\Seeder;

class SignatureItemCostsSeeder extends Seeder
{
    /**
     * Custos por nível (extraídos de /guias/signature-items).
     * Levels 39-40 não documentados no guide — mantidos null pra não inventar valor.
     *
     * @var array<int, int|null>
     */
    private const COSTS = [
        // +1 a +10 (Primordial Emblems — total 200)
        1 => 10, 2 => 10, 3 => 10, 4 => 10,
        5 => 15, 6 => 15, 7 => 15,
        8 => 25, 9 => 30, 10 => 50,

        // +11 a +20 (Primordial Emblems — total 240)
        11 => 10, 12 => 10,
        13 => 15, 14 => 15,
        15 => 20,
        16 => 25, 17 => 25,
        18 => 30,
        19 => 40, 20 => 40,

        // +21 a +30 (Amplifying Emblems — total 300)
        21 => 10, 22 => 20,
        23 => 20, 24 => 20,
        25 => 30, 26 => 30,
        27 => 40, 28 => 40, 29 => 40,
        30 => 50,

        // +31 a +40 (Faction Emblems — só Celestial/Hypogean)
        31 => 50, 32 => 50, 33 => 50,
        34 => 60, 35 => 70, 36 => 80, 37 => 90, 38 => 120,
        39 => null, 40 => null, // não documentado no guide
    ];

    public function run(): void
    {
        foreach (self::COSTS as $level => $cost) {
            $emblemType = match (true) {
                $level <= 20 => SignatureItemCost::EMBLEM_PRIMORDIAL,
                $level <= 30 => SignatureItemCost::EMBLEM_AMPLIFYING,
                default => SignatureItemCost::EMBLEM_FACTION,
            };

            SignatureItemCost::updateOrCreate(
                ['level' => $level],
                ['cost' => $cost, 'emblem_type' => $emblemType],
            );
        }
    }
}
