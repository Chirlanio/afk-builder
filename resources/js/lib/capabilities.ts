// Capacidades dos heróis (HeroCapabilityClassifier) — rótulos e agrupamento para a UI.

export type CapabilityTag =
    | 'damage_aoe'
    | 'damage_single'
    | 'damage_over_time'
    | 'healing'
    | 'shield'
    | 'damage_reduction'
    | 'self_sustain'
    | 'revive'
    | 'cc_hard'
    | 'cc_soft'
    | 'cc_immunity'
    | 'dispel'
    | 'energy_gain'
    | 'energy_drain'
    | 'buff_ally'
    | 'debuff_enemy'
    | 'backline_access'
    | 'frontline';

export type CapabilityGroup = 'damage' | 'sustain' | 'control' | 'support' | 'position';

interface CapabilityMeta {
    label: string;
    description: string;
    group: CapabilityGroup;
}

export const CAPABILITIES: Record<CapabilityTag, CapabilityMeta> = {
    damage_aoe: { label: 'Dano em área', description: 'Atinge vários inimigos de uma vez.', group: 'damage' },
    damage_single: { label: 'Dano em alvo único', description: 'Concentra dano em um inimigo — bom contra chefes e carries.', group: 'damage' },
    damage_over_time: { label: 'Dano contínuo', description: 'Dano ao longo do tempo (sangramento, veneno, zonas).', group: 'damage' },
    healing: { label: 'Cura do time', description: 'Recupera a vida dos aliados.', group: 'sustain' },
    shield: { label: 'Escudo', description: 'Protege aliados absorvendo dano.', group: 'sustain' },
    damage_reduction: { label: 'Redução de dano', description: 'Faz aliados receberem menos dano.', group: 'sustain' },
    self_sustain: { label: 'Autossustentação', description: 'Roubo de vida ou cura própria.', group: 'sustain' },
    revive: { label: 'Sobrevive / revive', description: 'Evita a morte ou volta à batalha.', group: 'sustain' },
    cc_hard: { label: 'Controle forte', description: 'Stun, congelar, derrubar, puxar, encantar.', group: 'control' },
    cc_soft: { label: 'Controle leve', description: 'Silenciar, lentidão, provocar.', group: 'control' },
    cc_immunity: { label: 'Imune a controle', description: 'Não pode ser controlado durante a habilidade.', group: 'control' },
    dispel: { label: 'Dissipar', description: 'Remove buffs inimigos ou debuffs aliados.', group: 'support' },
    energy_gain: { label: 'Energia para aliados', description: 'Acelera os ultimates do time.', group: 'support' },
    energy_drain: { label: 'Roubo de energia', description: 'Atrasa os ultimates inimigos.', group: 'support' },
    buff_ally: { label: 'Buff de aliados', description: 'Aumenta ataque, crítico ou velocidade do time.', group: 'support' },
    debuff_enemy: { label: 'Debuff de inimigos', description: 'Reduz armadura, precisão ou aumenta o dano recebido.', group: 'support' },
    backline_access: { label: 'Alcança a linha de trás', description: 'Ataca o inimigo mais distante ou de menor vida.', group: 'position' },
    frontline: { label: 'Linha de frente', description: 'Segura o dano na frente do time.', group: 'position' },
};

export const CAPABILITY_GROUP_CLASS: Record<CapabilityGroup, string> = {
    damage: 'border-rose-400/30 bg-rose-500/10 text-rose-200',
    sustain: 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
    control: 'border-violet-400/30 bg-violet-500/10 text-violet-200',
    support: 'border-amber-400/30 bg-amber-500/10 text-amber-200',
    position: 'border-sky-400/30 bg-sky-500/10 text-sky-200',
};

export function capabilityMeta(tag: string): CapabilityMeta | null {
    return (CAPABILITIES as Record<string, CapabilityMeta>)[tag] ?? null;
}

/** Análise informativa do time (TeamCapabilityAnalyzer) — vem em score.details.capabilities. */
export interface TeamCapabilityAnalysis {
    kind: 'pve' | 'pvp' | 'boss';
    has_profiles: boolean;
    covered: Partial<Record<CapabilityTag, string[]>>;
    missing: CapabilityTag[];
    ineffective: Partial<Record<CapabilityTag, string[]>>;
    no_sustain: boolean;
}

export const TEAM_KIND_LABEL: Record<TeamCapabilityAnalysis['kind'], string> = {
    pve: 'PvE',
    pvp: 'PvP',
    boss: 'Chefe',
};
