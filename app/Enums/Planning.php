<?php

namespace App\Enums;

/**
 * Como uma Formação é montada: real (só heróis do Roster, no investimento
 * atual) ou alvo (qualquer herói do jogo, no Investimento máximo).
 */
enum Planning: string
{
    case Real = 'real';
    case Target = 'target';
}
