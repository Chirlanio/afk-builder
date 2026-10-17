import type { Beast } from '@/types/afk';

export const BEAST_MAX_LEVEL = 36;

const STAT_LABELS: Record<string, string> = {
    hp: 'PV',
    attack: 'ATQ',
    defense: 'DEF',
    crit_rate: 'Crit',
    crit_amp: 'Crit Amp',
    dodge: 'Esquiva',
    vitality: 'Vit',
    perception: 'Percep',
    proficiency: 'Compet',
    magic_resistance: 'RM',
    crit_block_rate: 'Bloq',
};

export type BeastScope = 'strength' | 'intelligence' | 'agility' | 'team';

export function beastBuffsByScope(beast: Beast | null | undefined, scope: BeastScope): Record<string, number> {
    if (!beast) return {};
    switch (scope) {
        case 'strength':
            return beast.buffs_strength ?? {};
        case 'intelligence':
            return beast.buffs_intelligence ?? {};
        case 'agility':
            return beast.buffs_agility ?? {};
        case 'team':
            return beast.buffs_team ?? {};
    }
}

/**
 * Conta quantos stats não-zero existem em cada scope da besta.
 */
export function beastStatCounts(beast: Beast | null | undefined): {
    strength: number;
    intelligence: number;
    agility: number;
    team: number;
    total: number;
} {
    const count = (obj: Record<string, number> | null | undefined) => {
        if (!obj) return 0;
        return Object.values(obj).filter((v) => Number(v) > 0).length;
    };
    const s = count(beast?.buffs_strength);
    const i = count(beast?.buffs_intelligence);
    const a = count(beast?.buffs_agility);
    const t = count(beast?.buffs_team);
    return { strength: s, intelligence: i, agility: a, team: t, total: s + i + a + t };
}

/**
 * Soma de todos os valores percentuais (sem escala de level). Útil pra ordenação/filtro.
 */
export function beastTotalPotential(beast: Beast | null | undefined): number {
    const sum = (obj: Record<string, number> | null | undefined) => {
        if (!obj) return 0;
        return Object.values(obj).reduce((acc, v) => acc + Number(v || 0), 0);
    };
    return sum(beast?.buffs_strength) + sum(beast?.buffs_intelligence) + sum(beast?.buffs_agility) + sum(beast?.buffs_team);
}

/**
 * Resume buffs num scope como string curta: "ATQ +20% · Crit +15%". Limita N stats.
 */
export function summarizeScope(buffs: Record<string, number> | null | undefined, limit = 3): string {
    if (!buffs) return '';
    const entries = Object.entries(buffs)
        .filter(([, v]) => Number(v) > 0)
        .sort((a, b) => Number(b[1]) - Number(a[1]))
        .slice(0, limit);
    if (entries.length === 0) return '';
    return entries.map(([key, value]) => `${STAT_LABELS[key] ?? key} +${Number(value).toFixed(0)}%`).join(' · ');
}

/**
 * Multiplica todos os valores dos buffs pelo factor (level / 36). Usado pra exibir
 * valores reais no level escolhido pelo user.
 */
export function buffsAtLevel(buffs: Record<string, number> | null | undefined, level: number): Record<string, number> {
    if (!buffs) return {};
    const factor = Math.max(1, Math.min(BEAST_MAX_LEVEL, level)) / BEAST_MAX_LEVEL;
    const out: Record<string, number> = {};
    for (const [key, value] of Object.entries(buffs)) {
        out[key] = Number(value) * factor;
    }
    return out;
}

export const STAT_LABEL = STAT_LABELS;
