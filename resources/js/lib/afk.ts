import type { LucideIcon } from 'lucide-react';
import { Brain, Crosshair, Dumbbell, Heart, Shield, Sparkles, Sword, Wind } from 'lucide-react';
import type { ClassCode, FactionCode, HeroType, TierCode } from '@/types/afk';

export interface FactionTheme {
    label: string;
    cardGradient: string;
    cardBorder: string;
    cardHoverGlow: string;
    headerBg: string;
    headerText: string;
    accent: string;
}

export const FACTION_THEME: Record<FactionCode, FactionTheme> = {
    lightbearer: {
        label: 'Portadores da Luz',
        cardGradient: 'bg-gradient-to-br from-amber-400/30 via-yellow-500/10 to-transparent',
        cardBorder: 'border-amber-400/50',
        cardHoverGlow: 'hover:shadow-amber-400/40',
        headerBg: 'bg-gradient-to-r from-amber-500/30 via-yellow-500/20 to-transparent',
        headerText: 'text-amber-700 dark:text-amber-300',
        accent: 'text-amber-500',
    },
    mauler: {
        label: 'Perversos',
        cardGradient: 'bg-gradient-to-br from-orange-500/30 via-amber-700/10 to-transparent',
        cardBorder: 'border-orange-500/50',
        cardHoverGlow: 'hover:shadow-orange-500/40',
        headerBg: 'bg-gradient-to-r from-orange-500/30 via-amber-700/20 to-transparent',
        headerText: 'text-orange-700 dark:text-orange-300',
        accent: 'text-orange-500',
    },
    wilder: {
        label: 'Selvagens',
        cardGradient: 'bg-gradient-to-br from-emerald-500/30 via-green-700/10 to-transparent',
        cardBorder: 'border-emerald-500/50',
        cardHoverGlow: 'hover:shadow-emerald-500/40',
        headerBg: 'bg-gradient-to-r from-emerald-500/30 via-green-700/20 to-transparent',
        headerText: 'text-emerald-700 dark:text-emerald-300',
        accent: 'text-emerald-500',
    },
    graveborn: {
        label: 'Tumulares',
        cardGradient: 'bg-gradient-to-br from-purple-500/30 via-violet-800/10 to-transparent',
        cardBorder: 'border-purple-500/50',
        cardHoverGlow: 'hover:shadow-purple-500/40',
        headerBg: 'bg-gradient-to-r from-purple-500/30 via-violet-800/20 to-transparent',
        headerText: 'text-purple-700 dark:text-purple-300',
        accent: 'text-purple-500',
    },
    celestial: {
        label: 'Celestiais',
        cardGradient: 'bg-gradient-to-br from-sky-300/30 via-cyan-200/10 to-transparent',
        cardBorder: 'border-sky-300/60',
        cardHoverGlow: 'hover:shadow-sky-300/40',
        headerBg: 'bg-gradient-to-r from-sky-300/30 via-cyan-200/20 to-transparent',
        headerText: 'text-sky-700 dark:text-sky-200',
        accent: 'text-sky-400',
    },
    hypogean: {
        label: 'Hipogeus',
        cardGradient: 'bg-gradient-to-br from-red-800/40 via-zinc-900/20 to-transparent',
        cardBorder: 'border-red-800/60',
        cardHoverGlow: 'hover:shadow-red-700/40',
        headerBg: 'bg-gradient-to-r from-red-800/40 via-zinc-900/30 to-transparent',
        headerText: 'text-red-700 dark:text-red-300',
        accent: 'text-red-600',
    },
    dimensional: {
        label: 'Dimensionais',
        cardGradient: 'bg-gradient-to-br from-blue-500/30 via-indigo-600/10 to-transparent',
        cardBorder: 'border-blue-500/50',
        cardHoverGlow: 'hover:shadow-blue-500/40',
        headerBg: 'bg-gradient-to-r from-blue-500/30 via-indigo-600/20 to-transparent',
        headerText: 'text-blue-700 dark:text-blue-300',
        accent: 'text-blue-500',
    },
    draconis: {
        label: 'Draconis',
        cardGradient: 'bg-gradient-to-br from-rose-500/30 via-red-700/10 to-transparent',
        cardBorder: 'border-rose-500/50',
        cardHoverGlow: 'hover:shadow-rose-500/40',
        headerBg: 'bg-gradient-to-r from-rose-500/30 via-red-700/20 to-transparent',
        headerText: 'text-rose-700 dark:text-rose-300',
        accent: 'text-rose-500',
    },
};

export const CLASS_ICON: Record<ClassCode, LucideIcon> = {
    tank: Shield,
    warrior: Sword,
    ranger: Crosshair,
    mage: Sparkles,
    support: Heart,
};

export const CLASS_LABEL: Record<ClassCode, string> = {
    tank: 'Tanque',
    warrior: 'Guerreiro',
    ranger: 'Atirador',
    mage: 'Mago',
    support: 'Suporte',
};

export const HERO_TYPE_LABEL: Record<HeroType, string> = {
    strength: 'Força',
    intelligence: 'Inteligência',
    agility: 'Agilidade',
};

export const HERO_TYPE_ICON: Record<HeroType, LucideIcon> = {
    strength: Dumbbell,
    intelligence: Brain,
    agility: Wind,
};

export const HERO_TYPE_BADGE: Record<HeroType, string> = {
    strength: 'bg-rose-500/20 text-rose-700 dark:text-rose-300 border-rose-500/40',
    intelligence: 'bg-sky-500/20 text-sky-700 dark:text-sky-300 border-sky-500/40',
    agility: 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-500/40',
};

export interface TierTheme {
    label: string;
    badge: string;
    maxLevel: number;
}

export const TIER_THEME: Record<TierCode, TierTheme> = {
    common: {
        label: 'Comum',
        badge: 'bg-stone-500 text-white',
        maxLevel: 100,
    },
    legendary: {
        label: 'Lendário',
        badge: 'bg-violet-600 text-white',
        maxLevel: 160,
    },
    ascended: {
        label: 'Ascendido',
        badge: 'bg-gradient-to-r from-amber-400 to-yellow-500 text-stone-900',
        maxLevel: 240,
    },
};

export interface AscensionStage {
    code: string;
    label: string;
    value: number;
    minTierMaxLevel: number;
}

/**
 * Stages de ascensão na ordem de progressão. `value` é monotônico para
 * comparações no algoritmo. `minTierMaxLevel` indica o tier mínimo do herói
 * para que esse stage seja válido (heróis Common só vão até common; Legendary
 * vão até mythic_plus; Ascended vão até ascended_5_stars).
 */
export const ASCENSION_STAGES: AscensionStage[] = [
    { code: 'common', label: 'Comum', value: 0, minTierMaxLevel: 100 },
    { code: 'common_plus', label: 'Comum+', value: 1, minTierMaxLevel: 100 },
    { code: 'rare', label: 'Raro', value: 2, minTierMaxLevel: 100 },
    { code: 'rare_plus', label: 'Raro+', value: 3, minTierMaxLevel: 100 },
    { code: 'elite', label: 'Elite', value: 4, minTierMaxLevel: 160 },
    { code: 'elite_plus', label: 'Elite+', value: 5, minTierMaxLevel: 160 },
    { code: 'legendary', label: 'Lendário', value: 6, minTierMaxLevel: 160 },
    { code: 'legendary_plus', label: 'Lendário+', value: 7, minTierMaxLevel: 160 },
    { code: 'mythic', label: 'Mítico', value: 8, minTierMaxLevel: 160 },
    { code: 'mythic_plus', label: 'Mítico+', value: 9, minTierMaxLevel: 160 },
    { code: 'ascended', label: 'Ascendido', value: 10, minTierMaxLevel: 240 },
    { code: 'ascended_plus', label: 'Ascendido+', value: 11, minTierMaxLevel: 240 },
    { code: 'ascended_1_star', label: 'Ascendido 1★', value: 12, minTierMaxLevel: 240 },
    { code: 'ascended_2_stars', label: 'Ascendido 2★', value: 13, minTierMaxLevel: 240 },
    { code: 'ascended_3_stars', label: 'Ascendido 3★', value: 14, minTierMaxLevel: 240 },
    { code: 'ascended_4_stars', label: 'Ascendido 4★', value: 15, minTierMaxLevel: 240 },
    { code: 'ascended_5_stars', label: 'Ascendido 5★', value: 16, minTierMaxLevel: 240 },
];

export function ascensionStagesForTier(tierMaxLevel: number): AscensionStage[] {
    return ASCENSION_STAGES.filter((stage) => stage.minTierMaxLevel <= tierMaxLevel);
}

/**
 * Mapeia o `ascension_value` (0-16) atual do user para o tier visualmente
 * "atingido". Asc 0-3 (Comum/Raro) → comum; 4-9 (Elite/Lendário/Mítico+) →
 * lendário; 10+ (Ascendido em diante) → ascendido.
 *
 * Use quando quiser mostrar o tier ATUAL do herói no roster (em vez do max
 * potencial dele). Sem ascension_value definido (null/0), retorna 'common'.
 */
export function tierFromAscension(ascensionValue: number | null | undefined): TierCode {
    const v = ascensionValue ?? 0;
    if (v >= 10) return 'ascended';
    if (v >= 4) return 'legendary';
    return 'common';
}

/**
 * Retorna o valor de um campo no idioma escolhido, com fallback para o original (EN).
 * Para PT-BR, usa `field_pt` se preenchido; senão devolve o `field` original.
 */
export function localized<T extends Record<string, unknown>>(
    item: T | null | undefined,
    field: string,
    locale: 'en' | 'pt' | undefined,
): string | null {
    if (!item) return null;
    if (locale === 'pt') {
        const ptValue = item[`${field}_pt`];
        if (typeof ptValue === 'string' && ptValue.trim() !== '') {
            return ptValue;
        }
    }
    const value = item[field];
    return typeof value === 'string' && value.trim() !== '' ? value : null;
}
