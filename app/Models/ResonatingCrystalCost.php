<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResonatingCrystalCost extends Model
{
    protected $primaryKey = 'level';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['level', 'gold_cost', 'hero_exp_cost', 'dust_cost'];

    protected $casts = [
        'level' => 'integer',
        'gold_cost' => 'integer',
        'hero_exp_cost' => 'integer',
        'dust_cost' => 'integer',
    ];

    /**
     * Retorna o custo do próximo nível (level+1). Null se não houver dado.
     */
    public static function forLevel(int $level): ?self
    {
        return static::query()->where('level', $level)->first();
    }
}
