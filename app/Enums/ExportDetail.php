<?php

namespace App\Enums;

/**
 * Quanto do herói vai no Export para IA: a ficha completa (tipo, papel,
 * habilidades, mobília, gravação e destaques) ou só a linha resumida.
 */
enum ExportDetail: string
{
    case Full = 'full';
    case Compact = 'compact';
}
