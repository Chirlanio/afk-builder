<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class FavoriteHeroPivot extends Pivot
{
    protected $table = 'user_hero_favorites';
}
