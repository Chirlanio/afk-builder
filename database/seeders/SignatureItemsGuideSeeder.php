<?php

namespace Database\Seeders;

use App\Models\Guide;
use Illuminate\Database\Seeder;

/**
 * Guia sobre custos e mecânica dos Signature Items, curado do dump
 * do afk.guide capturado pelo usuário via extensão Claude Chrome.
 */
class SignatureItemsGuideSeeder extends Seeder
{
    public function run(): void
    {
        $titlePt = 'Signature Items — Custos e Mecânica';
        $titleEn = 'Signature Items — Costs and Mechanics';
        $summaryPt = 'Como destravar e upgradar Signature Items (SI): requisitos, custos por nível e tipos de Emblema.';
        $summaryEn = 'How to unlock and upgrade Signature Items (SI): requirements, level costs, and Emblem types.';

        $contentPt = <<<'MD'
## O que são Signature Items

**Signature Items (SI)** são itens únicos por herói que destravam habilidades passivas adicionais. Cada herói (exceto os Legendários sem SI — ver abaixo) tem um SI que escala de **+0 até +30** (ou +40 para algumas facções), aumentando o efeito da passiva.

## Como destravar

Para destravar o SI inicial (+0), são necessários **20 Primordial Emblems** específicos do herói.

## Faixas de upgrade e tipos de emblema

| Faixa  | Tipo de emblema             | Aplica a       |
|--------|------------------------------|----------------|
| +1 a +10  | Primordial Emblems        | Todos          |
| +11 a +20 | Primordial Emblems        | Todos          |
| +21 a +30 | Amplifying Emblems        | Todos          |
| +31 a +40 | Emblemas de facção        | **Apenas Celestial e Hypogean** |

> Heróis Lightbearer/Mauler/Wilder/Graveborn/Dimensional **não passam de +30**. O cap em +40 é exclusivo das facções Celestial e Hypogean.

## Custo detalhado por nível

### Faixa +1 a +10 (Total: 200 emblemas)

| Nível | Custo |
|-------|-------|
| 1     | 10    |
| 2     | 10    |
| 3     | 10    |
| 4     | 10    |
| 5     | 15    |
| 6     | 15    |
| 7     | 15    |
| 8     | 25    |
| 9     | 30    |
| 10    | 50    |

### Faixa +11 a +20 (Total: 240 emblemas)

| Nível | Custo |
|-------|-------|
| 11–12 | 10    |
| 13–14 | 15    |
| 15    | 20    |
| 16–17 | 25    |
| 18    | 30    |
| 19–20 | 40    |

### Faixa +21 a +30 (Total: 300 emblemas — Amplifying Emblems)

| Nível | Custo |
|-------|-------|
| 21–22 | 10–20 |
| 23–24 | 20    |
| 25–26 | 30    |
| 27–29 | 40    |
| 30    | 50    |

### Faixa +31 a +40 (Total: 1050 emblemas — Emblemas de facção, **só Celestial/Hypogean**)

| Nível | Custo |
|-------|-------|
| 31–33 | 50    |
| 34    | 60    |
| 35    | 70    |
| 36    | 80    |
| 37    | 90    |
| 38    | 120   |

## Heróis legendários sem Signature Item

Estes heróis são "lendários" no sentido antigo do jogo e **não possuem SI** (cap natural no nível 160 do herói):

- Angelo, Ankhira, Arden, Niru
- Hogan, Golus, Ira, Silvina
- Mirael, Saveas, Ogi, Vedan, Morvus

> Para esses heróis, o investimento se concentra em **Furniture** e **Engravings** (E30/E60).

## Estratégia de investimento

- **+10:** retorno alto, custo baixo. Faça em todos os heróis principais cedo.
- **+20:** Curva começa a subir. Priorize 1-2 carries (ex: Daimon, Saurus, Lucretia).
- **+30:** Investimento pesado. Reserve para heróis-chave do seu time PvP/Twisted Realm.
- **+40:** Apenas para Celestials/Hypogeans MUITO usados (Talene, Twins, Ezizh, Mehira, Lucretia).

---

*Fonte: [afk.guide](https://afk.guide/signature-items/)*
MD;

        $contentEn = <<<'MD'
## What are Signature Items

**Signature Items (SI)** are unique per-hero items that unlock additional passive abilities. Each hero (except the Legendary ones without SI — see below) has a SI that scales from **+0 up to +30** (or +40 for some factions), boosting the passive effect.

## Unlocking

To unlock the initial SI (+0), you need **20 Primordial Emblems** specific to the hero.

## Upgrade ranges and Emblem types

| Range  | Emblem type                  | Applies to     |
|--------|------------------------------|----------------|
| +1 to +10  | Primordial Emblems       | All            |
| +11 to +20 | Primordial Emblems       | All            |
| +21 to +30 | Amplifying Emblems       | All            |
| +31 to +40 | Faction Emblems          | **Celestial & Hypogean only** |

> Lightbearer/Mauler/Wilder/Graveborn/Dimensional heroes **cap at +30**. The +40 cap is exclusive to Celestial and Hypogean.

## Cost details — see PT version for full tables.

*Source: [afk.guide](https://afk.guide/signature-items/)*
MD;

        Guide::updateOrCreate(
            ['slug' => 'signature-items'],
            [
                'title' => $titleEn,
                'title_pt' => $titlePt,
                'category' => 'mecanicas',
                'summary' => $summaryEn,
                'summary_pt' => $summaryPt,
                'content' => $contentEn,
                'content_pt' => $contentPt,
                'source_url' => 'https://afk.guide/signature-items/',
                'display_order' => 90,
            ],
        );
    }
}
