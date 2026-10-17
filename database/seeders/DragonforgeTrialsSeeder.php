<?php

namespace Database\Seeders;

use App\Models\Hero;
use App\Models\HeroRating;
use App\Models\RecommendedFormation;
use Illuminate\Database\Seeder;

/**
 * Provas da Forja Dracônica (Dragonforge Trials): formações por fase do guia
 * afk.guide/dragonforge-trials-guide (consultado em 2026-10-18) e ratings do
 * modo derivados delas.
 *
 * - Cada formação vira um RecommendedFormation de várias equipes (`teams`):
 *   2 equipes nas fases 3-6, 3 nas fases 7-12. O guia não lista as fases 1-2.
 * - Ratings (mode=dragonforge, stage=end, source=afk.guide): frequência de cada
 *   herói nas formações, ponderada pelas estrelas (3★=1.0, 2★=0.7, 1★=0.5).
 *   Ranking → S+ (top 10%), S (20%), A (30%), B (resto). Quem não aparece: A se
 *   for Dracônico (nível 600 fixo, sem supressão no modo), C nos demais.
 *   São DERIVADOS das escolhas do guia — refletem a comunidade, não uma
 *   avaliação independente.
 *
 * Idempotente. Heróis fora do catálogo (ex: Daemina) ficam com o slug nas
 * formações e passam a ser reconhecidos quando o sync os importar.
 */
class DragonforgeTrialsSeeder extends Seeder
{
    private const SOURCE_URL = 'https://afk.guide/dragonforge-trials-guide/';

    private const STAR_WEIGHT = [3 => 1.0, 2 => 0.7, 1 => 0.5];

    /** [fase, estrelas, [[equipe 1], [equipe 2], ...]] — na ordem do guia. */
    private const LINEUPS = [
        [3, 3, [['shemira-the-soulreaver', 'ivan', 'emilia', 'rem', 'hildwin'], ['tamrus', 'solise-water-animator', 'liberta', 'naroko', 'nyla']]],
        [3, 3, [['rem', 'alna', 'nyla', 'emilia', 'liberta'], ['athalia-harbinger-of-truth', 'jerome', 'lyca-the-dawnbringer', 'cassius', 'lucilla']]],
        [3, 3, [['lucilla', 'baden-knight-of-blight', 'liberta', 'athalia-harbinger-of-truth', 'lavatune'], ['lucius-oath-of-radiance', 'shemira-the-soulreaver', 'gwyneth-winged-vanquisher', 'ivan', 'lyca-the-dawnbringer']]],
        [3, 3, [['shemira-the-soulreaver', 'athalia-harbinger-of-truth', 'safiya-maestro-of-power', 'nyla', 'liberta'], ['pulina', 'cassius', 'atheus', 'lyca-the-dawnbringer', 'ivan']]],
        [4, 3, [['lucilla', 'cassius', 'pulina', 'emilia', 'nyla'], ['brutus-lions-pride', 'shemira-the-soulreaver', 'safiya-maestro-of-power', 'hildwin', 'liberta']]],
        [4, 3, [['shemira-the-soulreaver', 'athalia-harbinger-of-truth', 'safiya-maestro-of-power', 'hildwin', 'liberta'], ['lucilla', 'pulina', 'lavatune', 'lyca-the-dawnbringer', 'emilia']]],
        [4, 3, [['shemira-the-soulreaver', 'cassius', 'emilia', 'gwyneth-winged-vanquisher', 'liberta'], ['brutus-lions-pride', 'pulina', 'hildwin', 'ivan', 'lucilla']]],
        [4, 3, [['shemira-the-soulreaver', 'pulina', 'athalia-harbinger-of-truth', 'ivan', 'lavatune'], ['lucilla', 'cassius', 'liberta', 'emilia', 'solise-water-animator']]],
        [4, 3, [['pulina', 'jerome', 'emilia', 'lavatune', 'gwyneth-winged-vanquisher'], ['cassius', 'shemira-the-soulreaver', 'nyla', 'liberta', 'hildwin']]],
        [4, 3, [['shemira-the-soulreaver', 'ivan', 'safiya-maestro-of-power', 'gwyneth-winged-vanquisher', 'liberta'], ['hildwin', 'rowan', 'pulina', 'jerome', 'nyla']]],
        [4, 3, [['brutus-lions-pride', 'cassius', 'hildwin', 'liberta', 'jerome'], ['kalthin', 'shemira-the-soulreaver', 'rem', 'nyla', 'emilia']]],
        [4, 3, [['shemira-the-soulreaver', 'ivan', 'liberta', 'hildwin', 'lavatune'], ['gavus-keeper-of-order', 'lucilla', 'pulina', 'nyla', 'gwyneth-winged-vanquisher']]],
        [4, 3, [['shemira-the-soulreaver', 'nyla', 'shuna', 'gwyneth-winged-vanquisher', 'emilia'], ['jerome', 'atheus', 'hildwin', 'ivan', 'tamrus']]],
        [5, 3, [['ivan', 'shemira-the-soulreaver', 'safiya-maestro-of-power', 'gwyneth-winged-vanquisher', 'palmer'], ['hildwin', 'lucilla', 'liberta', 'pulina', 'athalia-harbinger-of-truth']]],
        [5, 3, [['shemira-the-soulreaver', 'ivan', 'lavatune', 'gwyneth-winged-vanquisher', 'nyla'], ['cassius', 'pulina', 'liberta', 'lucilla', 'hildwin']]],
        [5, 3, [['jerome', 'palmer', 'liberta', 'gwyneth-winged-vanquisher', 'ivan'], ['pulina', 'shemira-the-soulreaver', 'nyla', 'yennefer', 'hildwin']]],
        [5, 3, [['lavatune', 'shemira-the-soulreaver', 'pulina', 'emilia', 'gwyneth-winged-vanquisher'], ['nyla', 'hildwin', 'cassius', 'liberta', 'lucilla']]],
        [5, 3, [['emilia', 'shemira-the-soulreaver', 'lavatune', 'gwyneth-winged-vanquisher', 'ivan'], ['cassius', 'hildwin', 'liberta', 'nyla', 'lucilla']]],
        [5, 3, [['ivan', 'shemira-the-soulreaver', 'hildwin', 'athalia-harbinger-of-truth', 'gwyneth-winged-vanquisher'], ['pulina', 'cassius', 'liberta', 'nyla', 'lucilla']]],
        [5, 3, [['shemira-the-soulreaver', 'gwyneth-winged-vanquisher', 'cassius', 'emilia', 'jerome'], ['lucius-oath-of-radiance', 'hildwin', 'pulina', 'safiya-maestro-of-power', 'ivan']]],
        [5, 2, [['solise-water-animator', 'cassius', 'pulina', 'emilia', 'nyla'], ['shemira-the-soulreaver', 'hildwin', 'liberta', 'palmer', 'ivan']]],
        [5, 2, [['naroko', 'brutus-lions-pride', 'nyla', 'hildwin', 'safiya-maestro-of-power'], ['shemira-the-soulreaver', 'cassius', 'lucilla', 'liberta', 'pulina']]],
        [5, 2, [['cassius', 'talene', 'ivan', 'palmer', 'hildwin'], ['pulina', 'shemira-the-soulreaver', 'liberta', 'nevanthi', 'nyla']]],
        [5, 2, [['nyla', 'shemira-the-soulreaver', 'lavatune', 'pulina', 'jerome'], ['hildwin', 'cassius', 'safiya-maestro-of-power', 'emilia', 'lucilla']]],
        [6, 3, [['ivan', 'nyla', 'safiya-maestro-of-power', 'pulina', 'hildwin'], ['shemira-the-soulreaver', 'gwyneth-winged-vanquisher', 'cassius', 'emilia', 'jerome']]],
        [6, 3, [['pulina', 'shemira-the-soulreaver', 'ivan', 'cassius', 'gwyneth-winged-vanquisher'], ['lucilla', 'jerome', 'liberta', 'safiya-maestro-of-power', 'hildwin']]],
        [6, 3, [['gwyneth-winged-vanquisher', 'cassius', 'shemira-the-soulreaver', 'emilia', 'nyla'], ['pulina', 'jerome', 'liberta', 'lavatune', 'hildwin']]],
        [6, 3, [['cassius', 'shemira-the-soulreaver', 'ivan', 'nyla', 'gwyneth-winged-vanquisher'], ['lucilla', 'jerome', 'pulina', 'liberta', 'hildwin']]],
        [6, 3, [['cassius', 'shemira-the-soulreaver', 'ivan', 'pulina', 'gwyneth-winged-vanquisher'], ['lucilla', 'jerome', 'raine', 'nyla', 'hildwin']]],
        [6, 2, [['cassius', 'pulina', 'shemira-the-soulreaver', 'emilia', 'nyla'], ['lucilla', 'jerome', 'liberta', 'lavatune', 'hildwin']]],
        [6, 2, [['cassius', 'pulina', 'shemira-the-soulreaver', 'ivan', 'nyla'], ['lucilla', 'jerome', 'liberta', 'safiya-maestro-of-power', 'hildwin']]],
        [6, 2, [['cassius', 'shemira-the-soulreaver', 'nyla', 'tamrus', 'gwyneth-winged-vanquisher'], ['lucilla', 'jerome', 'liberta', 'emilia', 'hildwin']]],
        [6, 2, [['pulina', 'shemira-the-soulreaver', 'nyla', 'cassius', 'gwyneth-winged-vanquisher'], ['lucilla', 'jerome', 'liberta', 'emilia', 'hildwin']]],
        [7, 3, [['pulina', 'brutus-lions-pride', 'safiya-maestro-of-power', 'malkrie', 'tamrus'], ['ivan', 'shemira-the-soulreaver', 'jerome', 'emilia', 'gwyneth-winged-vanquisher'], ['cassius', 'gavus-keeper-of-order', 'hildwin', 'nyla', 'liberta']]],
        [7, 3, [['jerome', 'belinda-spark-of-hope', 'palmer', 'hildwin', 'lavatune'], ['shemira-the-soulreaver', 'ivan', 'raine', 'emilia', 'gwyneth-winged-vanquisher'], ['tamrus', 'pulina', 'liberta', 'nyla', 'lucilla']]],
        [7, 3, [['cassius', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['hildwin', 'jerome', 'lavatune', 'safiya-maestro-of-power', 'nyla'], ['lucilla', 'pulina', 'athalia-harbinger-of-truth', 'palmer', 'liberta']]],
        [7, 3, [['jerome', 'lucius-oath-of-radiance', 'palmer', 'hildwin', 'lavatune'], ['ivan', 'shemira-the-soulreaver', 'raine', 'emilia', 'gwyneth-winged-vanquisher'], ['pulina', 'lucilla', 'liberta', 'nyla', 'safiya-maestro-of-power']]],
        [7, 3, [['jerome', 'veithael', 'lan', 'lavatune', 'gwyneth-winged-vanquisher'], ['athalia-harbinger-of-truth', 'shemira-the-soulreaver', 'ivan', 'emilia', 'hildwin'], ['lucilla', 'tamrus', 'safiya-maestro-of-power', 'cassius', 'liberta']]],
        [7, 3, [['naroko', 'cassius', 'eugene-fool-of-chaos', 'nyla', 'lan'], ['lucilla', 'jerome', 'liberta', 'emilia', 'hildwin'], ['shemira-the-soulreaver', 'gwyneth-winged-vanquisher', 'ivan', 'tamrus', 'pulina']]],
        [7, 2, [['shemira-the-soulreaver', 'atheus', 'safiya-maestro-of-power', 'pulina', 'ivan'], ['cassius', 'nyla', 'emilia', 'hildwin', 'liberta'], ['eugene-fool-of-chaos', 'jerome', 'gavus-keeper-of-order', 'palmer', 'lavatune']]],
        [7, 2, [['pulina', 'cassius', 'kalthin', 'nyla', 'safiya-maestro-of-power'], ['athalia-harbinger-of-truth', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['lucilla', 'atheus', 'gavus-keeper-of-order', 'hildwin', 'liberta']]],
        [7, 2, [['lucilla', 'cassius', 'hildwin', 'lyca-the-dawnbringer', 'liberta'], ['ivan', 'shemira-the-soulreaver', 'jerome', 'emilia', 'gwyneth-winged-vanquisher'], ['pulina', 'alna', 'naroko', 'nyla', 'safiya-maestro-of-power']]],
        [7, 2, [['pulina', 'alna', 'jerome', 'palmer', 'nyla'], ['treznor', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['cassius', 'atheus', 'hildwin', 'athalia-harbinger-of-truth', 'liberta']]],
        [7, 2, [['alna', 'pulina', 'rem', 'nyla', 'safiya-maestro-of-power'], ['atheus', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['eugene-fool-of-chaos', 'jerome', 'gavus-keeper-of-order', 'hildwin', 'liberta']]],
        [7, 2, [['tamrus', 'cassius', 'athalia-harbinger-of-truth', 'liberta', 'gwyneth-winged-vanquisher'], ['lucilla', 'shemira-the-soulreaver', 'nyla', 'emilia', 'hildwin'], ['pulina', 'lyca-the-dawnbringer', 'palmer', 'jerome', 'joan-of-arc']]],
        [8, 3, [['treznor', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['hildwin', 'jerome', 'gavus-keeper-of-order', 'palmer', 'lavatune'], ['lucilla', 'nyla', 'safiya-maestro-of-power', 'pulina', 'liberta']]],
        [8, 3, [['cassius', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['hildwin', 'jerome', 'athalia-harbinger-of-truth', 'palmer', 'lavatune'], ['lucilla', 'nyla', 'raku', 'pulina', 'liberta']]],
        [8, 3, [['ivan', 'shemira-the-soulreaver', 'raine', 'emilia', 'gwyneth-winged-vanquisher'], ['jerome', 'palmer', 'lavatune', 'hildwin', 'athalia-harbinger-of-truth'], ['lucilla', 'nyla', 'safiya-maestro-of-power', 'lan', 'liberta']]],
        [8, 2, [['joan-of-arc', 'cassius', 'nyla', 'emilia', 'atheus'], ['gavus-keeper-of-order', 'lucius-oath-of-radiance', 'hildwin', 'lavatune', 'jerome'], ['lucilla', 'shemira-the-soulreaver', 'ivan', 'pulina', 'liberta']]],
        [8, 2, [['treznor', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['cassius', 'atheus', 'naroko', 'pulina', 'nyla'], ['jerome', 'lucilla', 'liberta', 'palmer', 'hildwin']]],
        [8, 2, [['nyla', 'shemira-the-soulreaver', 'ivan', 'emilia', 'pulina'], ['palmer', 'tamrus', 'gavus-keeper-of-order', 'jerome', 'hildwin'], ['lucilla', 'baden-knight-of-blight', 'safiya-maestro-of-power', 'cassius', 'liberta']]],
        [8, 2, [['talene', 'shemira-the-soulreaver', 'ivan', 'gwyneth-winged-vanquisher', 'emilia'], ['pulina', 'cassius', 'naroko', 'nyla', 'safiya-maestro-of-power'], ['jerome', 'atheus', 'liberta', 'palmer', 'hildwin']]],
        [8, 2, [['treznor', 'shemira-the-soulreaver', 'ivan', 'gwyneth-winged-vanquisher', 'emilia'], ['cassius', 'pulina', 'lavatune', 'nyla', 'silas'], ['jerome', 'hildwin', 'safiya-maestro-of-power', 'palmer', 'liberta']]],
        [8, 2, [['treznor', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['cassius', 'atheus', 'joan-of-arc', 'pulina', 'nyla'], ['jerome', 'lucilla', 'liberta', 'lan', 'hildwin']]],
        [9, 3, [['cassius', 'pulina', 'safiya-maestro-of-power', 'nyla', 'jerome'], ['shemira-the-soulreaver', 'athalia-harbinger-of-truth', 'gwyneth-winged-vanquisher', 'emilia', 'ivan'], ['lucilla', 'hildwin', 'liberta', 'gavus-keeper-of-order', 'lavatune']]],
        [9, 3, [['cassius', 'pulina', 'safiya-maestro-of-power', 'melion', 'lan'], ['shemira-the-soulreaver', 'athalia-harbinger-of-truth', 'gwyneth-winged-vanquisher', 'jerome', 'ivan'], ['lucilla', 'hildwin', 'liberta', 'nyla', 'lavatune']]],
        [9, 3, [['safiya-maestro-of-power', 'cassius', 'rimuru', 'villanelle', 'lan'], ['shemira-the-soulreaver', 'ivan', 'gwyneth-winged-vanquisher', 'lavatune', 'pulina'], ['lucilla', 'hildwin', 'liberta', 'nyla', 'melion']]],
        [9, 3, [['lucilla', 'talene', 'silas', 'ivan', 'gwyneth-winged-vanquisher'], ['alna', 'cassius', 'liberta', 'pulina', 'nevanthi'], ['shemira-the-soulreaver', 'hildwin', 'nyla', 'emilia', 'jerome']]],
        [9, 3, [['lucilla', 'shemira-the-soulreaver', 'liberta', 'ivan', 'gwyneth-winged-vanquisher'], ['nyla', 'alna', 'pulina', 'atheus', 'safiya-maestro-of-power'], ['lucius-oath-of-radiance', 'jerome', 'eugene-fool-of-chaos', 'palmer', 'hildwin']]],
        [9, 2, [['alna', 'cassius', 'silas', 'pulina', 'safiya-maestro-of-power'], ['lucilla', 'shemira-the-soulreaver', 'robin-hood', 'nyla', 'liberta'], ['lucius-oath-of-radiance', 'jerome', 'gavus-keeper-of-order', 'palmer', 'hildwin']]],
        [9, 2, [['lucilla', 'shemira-the-soulreaver', 'liberta', 'ivan', 'gwyneth-winged-vanquisher'], ['pulina', 'brutus-lions-pride', 'nyla', 'cassius', 'safiya-maestro-of-power'], ['lucius-oath-of-radiance', 'jerome', 'gavus-keeper-of-order', 'lavatune', 'hildwin']]],
        [9, 2, [['pulina', 'nyla', 'cassius', 'safiya-maestro-of-power', 'emilia'], ['shemira-the-soulreaver', 'lucilla', 'gwyneth-winged-vanquisher', 'liberta', 'ivan'], ['solise-water-animator', 'hildwin', 'gavus-keeper-of-order', 'eugene-fool-of-chaos', 'jerome']]],
        [9, 2, [['veithael', 'shemira-the-soulreaver', 'ivan', 'emilia', 'gwyneth-winged-vanquisher'], ['alna', 'cassius', 'liberta', 'pulina', 'nevanthi'], ['atheus', 'jerome', 'safiya-maestro-of-power', 'nyla', 'hildwin']]],
        [9, 2, [['veithael', 'shemira-the-soulreaver', 'emilia', 'ivan', 'gwyneth-winged-vanquisher'], ['pulina', 'cassius', 'safiya-maestro-of-power', 'nyla', 'nevanthi'], ['jerome', 'hildwin', 'eugene-fool-of-chaos', 'gavus-keeper-of-order', 'lavatune']]],
        [10, 1, [['atheus', 'nyla', 'gwyneth-winged-vanquisher', 'emilia', 'daemina'], ['shemira-the-soulreaver', 'cassius', 'safiya-maestro-of-power', 'melion', 'lan'], ['hildwin', 'jerome', 'shuna', 'gavus-keeper-of-order', 'lavatune']]],
        [10, 1, [['pulina', 'alna', 'nyla', 'safiya-maestro-of-power', 'daemina'], ['shemira-the-soulreaver', 'cassius', 'ivan', 'melion', 'gwyneth-winged-vanquisher'], ['lan', 'jerome', 'shuna', 'lavatune', 'gavus-keeper-of-order']]],
        [10, 1, [['kalthin', 'alna', 'nyla', 'safiya-maestro-of-power', 'daemina'], ['shemira-the-soulreaver', 'pulina', 'ivan', 'melion', 'gwyneth-winged-vanquisher'], ['lan', 'jerome', 'shuna', 'lavatune', 'gavus-keeper-of-order']]],
        [10, 1, [['atheus', 'nyla', 'aurelia', 'emilia', 'daemina'], ['shemira-the-soulreaver', 'rimuru', 'ivan', 'lan', 'melion'], ['hildwin', 'jerome', 'shuna', 'lavatune', 'gavus-keeper-of-order']]],
        [10, 1, [['atheus', 'nyla', 'gwyneth-winged-vanquisher', 'liberta', 'daemina'], ['shemira-the-soulreaver', 'pulina', 'ivan', 'aurelia', 'lan'], ['hildwin', 'jerome', 'shuna', 'lavatune', 'gavus-keeper-of-order']]],
        [10, 1, [['eironn-frostglade-paladin', 'alna', 'nyla', 'safiya-maestro-of-power', 'daemina'], ['shemira-the-soulreaver', 'atheus', 'ivan', 'tamrus', 'gwyneth-winged-vanquisher'], ['hildwin', 'jerome', 'shuna', 'lavatune', 'gavus-keeper-of-order']]],
        [10, 3, [['shemira-the-soulreaver', 'ivan', 'gwyneth-winged-vanquisher', 'melion', 'lan'], ['sion', 'alna', 'nyla', 'safiya-maestro-of-power', 'daemina'], ['hildwin', 'jerome', 'velufira', 'gavus-keeper-of-order', 'lavatune']]],
        [10, 3, [['misha', 'sion', 'aurelia', 'shuna'], ['jerome', 'tamrus', 'gwyneth-winged-vanquisher', 'rem', 'lyca-the-dawnbringer'], ['shemira-the-soulreaver', 'hildwin', 'lan', 'lavatune', 'rimuru']]],
        [11, 3, [['hildwin', 'shemira-the-soulreaver', 'emilia', 'lavatune', 'rimuru'], ['misha', 'sion', 'ivan', 'shuna', 'melion'], ['jerome', 'tamrus', 'gwyneth-winged-vanquisher', 'lyca-the-dawnbringer', 'rem']]],
        [11, 3, [['velufira', 'envydiel-venomous-desire', 'aurelia', 'misha', 'tamrus'], ['sion', 'gavus-keeper-of-order', 'safiya-maestro-of-power', 'melion', 'jerome'], ['shemira-the-soulreaver', 'hildwin', 'emilia', 'lavatune', 'rimuru']]],
        [11, 3, [['velufira', 'lan', 'envydiel-venomous-desire', 'melion', 'skylan'], ['knox', 'alvida', 'sion', 'ivan', 'athalia-harbinger-of-truth'], ['leviathan', 'misha', 'umbriel', 'nyla', 'hildwin']]],
        [11, 3, [['shemira-the-soulreaver', 'leviathan', 'envydiel-venomous-desire', 'melion', 'lan'], ['sion', 'alna', 'nyla', 'safiya-maestro-of-power', 'daemina'], ['hildwin', 'jerome', 'velufira', 'gavus-keeper-of-order', 'lavatune']]],
        [12, 2, [['shemira-the-soulreaver', 'ivan', 'gwyneth-winged-vanquisher', 'melion', 'lan'], ['sion', 'alna', 'nyla', 'safiya-maestro-of-power', 'daemina'], ['hildwin', 'jerome', 'velufira', 'gavus-keeper-of-order', 'lavatune']]],
        [12, 2, [['shemira-the-soulreaver', 'hildwin', 'nyla', 'lavatune', 'ivan'], ['misha', 'leviathan', 'sion', 'aurelia'], ['jerome', 'tamrus', 'lan', 'zohra-stellar-radiance', 'lyca-the-dawnbringer']]],
        [12, 3, [['sion', 'jerome', 'misha', 'emilia', 'aurelia'], ['lan', 'melion', 'safiya-maestro-of-power', 'envydiel-venomous-desire', 'gavus-keeper-of-order'], ['shemira-the-soulreaver', 'hildwin', 'rimuru', 'lavatune', 'nyla']]],
        [12, 3, [['hildwin', 'shemira-the-soulreaver', 'emilia', 'lavatune', 'rimuru'], ['misha', 'shuna', 'melion', 'safiya-maestro-of-power', 'sion'], ['jerome', 'tamrus', 'gwyneth-winged-vanquisher', 'lyca-the-dawnbringer', 'rem']]],
        [12, 3, [['shemira-the-soulreaver', 'hildwin', 'athalia-harbinger-of-truth', 'lavatune', 'ivan'], ['misha', 'melion', 'sion', 'aurelia', 'nyla'], ['jerome', 'tamrus', 'zohra-stellar-radiance', 'velufira', 'lan']]],
    ];

    public function run(): void
    {
        $this->seedFormations();
        $this->seedRatings();
    }

    private function seedFormations(): void
    {
        $optionByStage = [];
        foreach (self::LINEUPS as [$stage, $stars, $teams]) {
            $option = $optionByStage[$stage] = ($optionByStage[$stage] ?? 0) + 1;

            RecommendedFormation::updateOrCreate(
                ['slug' => sprintf('dragonforge-f%02d-%02d', $stage, $option)],
                [
                    'name' => "Forja Dracônica · Fase {$stage} · Opção {$option}",
                    'mode' => RecommendedFormation::MODE_DRAGONFORGE,
                    'tier' => "{$stars}★",
                    'description' => sprintf(
                        'Fase %d — %d equipes, resultado de %d estrela%s no guia. Só Dracônicos, Dimensionais e heróis do Cristal Ressonante; coleções, artefatos e pets não valem.',
                        $stage, count($teams), $stars, $stars > 1 ? 's' : '',
                    ),
                    'notes' => $this->investmentNote($stage),
                    'hero_slugs' => array_merge(...$teams),
                    'teams' => $teams,
                    'core_count' => count(array_merge(...$teams)),
                    'artifacts' => [],
                    'source_url' => self::SOURCE_URL,
                    'source_label' => 'afk.guide / Dragonforge Trials',
                    'sort_order' => 1000 + $stage * 100 + (3 - $stars) * 20 + $option,
                    'is_active' => true,
                ],
            );
        }
    }

    /** Investimento mínimo por fase segundo o guia. */
    private function investmentNote(int $stage): ?string
    {
        return match (true) {
            $stage >= 11 => 'Investimento mínimo: SI 30, Móveis 9, Inscrição 30.',
            $stage === 10 => 'Investimento mínimo: SI 30, Móveis 9.',
            $stage === 9 => 'Investimento mínimo: SI 20, Móveis 3.',
            $stage >= 7 => 'Investimento mínimo: SI 10, Móveis 3.',
            default => null,
        };
    }

    private function seedRatings(): void
    {
        $score = [];
        foreach (self::LINEUPS as [, $stars, $teams]) {
            foreach (array_merge(...$teams) as $slug) {
                $score[$slug] = ($score[$slug] ?? 0) + self::STAR_WEIGHT[$stars];
            }
        }
        arsort($score);

        $ranked = array_keys($score);
        $total = count($ranked);
        $tierBySlug = [];
        foreach ($ranked as $position => $slug) {
            $percentile = $position / max(1, $total);
            $tierBySlug[$slug] = match (true) {
                $percentile < 0.10 => 'S+',
                $percentile < 0.30 => 'S',
                $percentile < 0.60 => 'A',
                default => 'B',
            };
        }

        $heroes = Hero::query()->with('faction:id,code')->where('is_active', true)->get(['id', 'slug', 'faction_id']);
        foreach ($heroes as $hero) {
            $tier = $tierBySlug[$hero->slug] ?? ($hero->faction?->code === 'draconis' ? 'A' : 'C');

            HeroRating::updateOrCreate(
                [
                    'hero_id' => $hero->id,
                    'mode' => HeroRating::MODE_DRAGONFORGE,
                    'stage' => HeroRating::STAGE_END,
                    'source' => 'afk.guide',
                ],
                [
                    'tier' => $tier,
                    'source_url' => self::SOURCE_URL,
                ],
            );
        }
    }
}
