<?php

namespace Database\Seeders;

use App\Models\Hero;
use App\Models\HeroCounter;
use Illuminate\Database\Seeder;

/**
 * Lista inicial de Counters, só com fonte pública citada (afk.guide). Não há
 * fonte estruturada de counters de PvP no AFK Arena: o "Counter Guide" cobre
 * heróis antigos e fala de fases (entra como PvE); a página "Best Team" traz
 * algumas relações de PvP. Os motivos são paráfrases em pt-BR (o site é "All
 * rights reserved"). A base cresce pelo admin (/admin/counters).
 *
 * Idempotente: atualiza pelo par + Contexto. Heróis fora do catálogo são
 * ignorados com aviso.
 */
class HeroCounterSeeder extends Seeder
{
    private const COUNTER_GUIDE = 'https://afk.guide/?p=2337';

    private const BEST_TEAM = 'https://afk.guide/best-team/';

    /**
     * [slug de quem countera, slug do alvo, Contexto, força 1-2, motivo, fonte]
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: int, 4: string, 5: string}>
     */
    public const COUNTERS = [
        // Counter Guide — Lightbearers
        ['fawkes', 'lucius', 'pve', 1, 'Desfaz a proteção que o Lucius espalha pelo time.', self::COUNTER_GUIDE],
        ['rosaline', 'lucius', 'pve', 1, 'O atordoamento curto interrompe o escudo do Lucius.', self::COUNTER_GUIDE],
        ['brutus', 'estrilda', 'pve', 1, 'Segura a investida da Estrilda.', self::COUNTER_GUIDE],
        ['hemora', 'estrilda', 'pve', 1, 'O charm dela funciona contra a Estrilda.', self::COUNTER_GUIDE],
        ['brutus', 'thane', 'pve', 1, 'Prende o Thane enquanto o seu dano se concentra nele.', self::COUNTER_GUIDE],
        ['rosaline', 'thane', 'pve', 1, 'Prende o Thane enquanto o seu dano se concentra nele.', self::COUNTER_GUIDE],
        ['nemora', 'thane', 'pve', 1, 'O controle dela neutraliza o Thane.', self::COUNTER_GUIDE],
        ['ferael', 'thane', 'pve', 1, 'O controle dele neutraliza o Thane.', self::COUNTER_GUIDE],
        // Counter Guide — Maulers
        ['athalia', 'safiya', 'pve', 1, 'Desarma o escudo que a Safiya cria no começo da luta.', self::COUNTER_GUIDE],
        ['nara', 'safiya', 'pve', 1, 'Desarma o escudo que a Safiya cria no começo da luta.', self::COUNTER_GUIDE],
        ['fawkes', 'khasos', 'pve', 1, 'Escapa da investida de abertura do Khasos.', self::COUNTER_GUIDE],
        ['fawkes', 'brutus', 'pve', 1, 'Elimina o Brutus logo no início, antes de a imunidade dele entrar.', self::COUNTER_GUIDE],
        ['nara', 'brutus', 'pve', 1, 'Elimina o Brutus logo no início, antes de a imunidade dele entrar.', self::COUNTER_GUIDE],
        // Counter Guide — Wilders
        ['nemora', 'arden', 'pve', 1, 'O charm vira o Arden contra o próprio time.', self::COUNTER_GUIDE],
        ['athalia', 'arden', 'pve', 1, 'Indicada pela fonte como resposta ao Arden.', self::COUNTER_GUIDE],
        ['nara', 'arden', 'pve', 1, 'Indicada pela fonte como resposta ao Arden.', self::COUNTER_GUIDE],
        ['rosaline', 'eironn', 'pve', 1, 'Recua e escapa da sucção do Eironn.', self::COUNTER_GUIDE],
        ['fawkes', 'eironn', 'pve', 1, 'Bane o Eironn logo depois da primeira sucção.', self::COUNTER_GUIDE],
        ['athalia', 'tasi', 'pve', 1, 'Indicada pela fonte como resposta à Tasi.', self::COUNTER_GUIDE],
        ['nara', 'tasi', 'pve', 1, 'Indicada pela fonte como resposta à Tasi.', self::COUNTER_GUIDE],
        ['brutus', 'tasi', 'pve', 1, 'Serve de isca: a Tasi gasta o banimento nele.', self::COUNTER_GUIDE],
        // Counter Guide — Graveborns
        ['fawkes', 'shemira', 'pve', 1, 'Tira de jogo a Shemira, a inimiga mais forte.', self::COUNTER_GUIDE],
        ['nemora', 'ferael', 'pve', 2, 'Counter forte: o charm desmonta o Ferael.', self::COUNTER_GUIDE],
        ['athalia', 'ferael', 'pve', 1, 'Neutraliza o Ferael.', self::COUNTER_GUIDE],
        ['nara', 'ferael', 'pve', 1, 'Neutraliza o Ferael.', self::COUNTER_GUIDE],
        ['arden', 'ferael', 'pve', 1, 'Indicado pela fonte como boa resposta ao Ferael.', self::COUNTER_GUIDE],
        ['rowan', 'kelthur', 'pve', 1, 'Segura a luta a favor do seu time contra o Kelthur.', self::COUNTER_GUIDE],
        ['rosaline', 'kelthur', 'pve', 1, 'Recua e escapa do Kelthur.', self::COUNTER_GUIDE],
        ['lyca', 'kelthur', 'pve', 1, 'Serve de isca descartável contra o Kelthur.', self::COUNTER_GUIDE],
        ['athalia', 'nara', 'pve', 1, 'Indicada pela fonte como resposta à Nara.', self::COUNTER_GUIDE],
        ['rosaline', 'nara', 'pve', 1, 'Indicada pela fonte como boa contra a Nara.', self::COUNTER_GUIDE],
        ['estrilda', 'grezhul', 'pve', 1, 'Com o item de assinatura, dá conta do Grezhul razoavelmente.', self::COUNTER_GUIDE],
        // Counter Guide — Celestials
        ['rosaline', 'athalia', 'pve', 1, 'Recua e foge do alcance da Athalia.', self::COUNTER_GUIDE],
        ['brutus', 'athalia', 'pve', 1, 'A imunidade dele segura a Athalia por um tempo.', self::COUNTER_GUIDE],
        ['lyca', 'athalia', 'pve', 1, 'Tanque de esquiva descartável que absorve a Athalia.', self::COUNTER_GUIDE],
        ['fawkes', 'athalia', 'pve', 1, 'Tanque de esquiva descartável que absorve a Athalia.', self::COUNTER_GUIDE],
        ['ferael', 'athalia', 'pve', 1, 'O medo funciona quando a Athalia chega perto.', self::COUNTER_GUIDE],
        // Best Team — PvE
        ['ezio', 'brutus', 'pve', 1, 'Sugerido pela fonte como troca contra o Brutus e times de Maulers.', self::BEST_TEAM],
        ['vurk', 'athalia', 'pve', 1, 'Sugerido pela fonte como troca contra times de Athalia que mergulham.', self::BEST_TEAM],
        // Best Team — PvP
        ['lorsan', 'ainz-ooal-gown', 'pvp', 1, 'O vínculo do Lorsan no Ainz faz o burst derrubá-lo mais rápido.', self::BEST_TEAM],
        ['gwyneth', 'ainz-ooal-gown', 'pvp', 1, 'O burst alto da Gwyneth derruba o Ainz rápido.', self::BEST_TEAM],
        ['athalia', 'eironn', 'pvp', 1, 'Interrompe o Eironn no início, antes de ele montar a jogada.', self::BEST_TEAM],
        ['nara', 'eironn', 'pvp', 1, 'Interrompe o Eironn no início, antes de ele montar a jogada.', self::BEST_TEAM],
        ['nakoruru', 'eironn', 'pvp', 1, 'Interrompe o Eironn no início, antes de ele montar a jogada.', self::BEST_TEAM],
        ['athalia', 'gwyneth', 'pvp', 1, 'Interrompe a Gwyneth no início da luta.', self::BEST_TEAM],
        ['nara', 'gwyneth', 'pvp', 1, 'Interrompe a Gwyneth no início da luta.', self::BEST_TEAM],
        ['nakoruru', 'gwyneth', 'pvp', 1, 'Interrompe a Gwyneth no início da luta.', self::BEST_TEAM],
    ];

    public function run(): void
    {
        $slugs = collect(self::COUNTERS)->flatMap(fn (array $counter) => [$counter[0], $counter[1]])->unique();
        $heroes = Hero::whereIn('slug', $slugs)->get(['id', 'slug', 'name'])->keyBy('slug');

        $missing = $slugs->diff($heroes->keys());
        if ($missing->isNotEmpty()) {
            $this->command?->warn('Counters ignorados (heróis fora do catálogo): '.$missing->implode(', '));
        }

        foreach (self::COUNTERS as [$hero, $target, $context, $strength, $reason, $url]) {
            // Herói fora do catálogo, ou as duas versões do mesmo Personagem: o domínio não aceita.
            if (! isset($heroes[$hero], $heroes[$target]) || $heroes[$hero]->characterKey() === $heroes[$target]->characterKey()) {
                continue;
            }

            HeroCounter::updateOrCreate(
                ['hero_id' => $heroes[$hero]->id, 'target_hero_id' => $heroes[$target]->id, 'context' => $context],
                ['strength' => $strength, 'reason' => $reason, 'source_url' => $url],
            );
        }
    }
}
