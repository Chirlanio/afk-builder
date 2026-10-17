<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    public const CATEGORY_MECANICAS = 'mecanicas';

    public const CATEGORY_MODOS = 'modos';

    public const CATEGORY_EVENTOS = 'eventos';

    public const CATEGORY_LABELS = [
        self::CATEGORY_MECANICAS => 'Mecânicas',
        self::CATEGORY_MODOS => 'Modos de jogo',
        self::CATEGORY_EVENTOS => 'Eventos',
    ];

    protected $fillable = [
        'slug',
        'title',
        'title_pt',
        'category',
        'summary',
        'summary_pt',
        'content',
        'content_pt',
        'source_url',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];
}
