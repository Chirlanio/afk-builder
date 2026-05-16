export type FactionCode =
    | 'lightbearer'
    | 'mauler'
    | 'wilder'
    | 'graveborn'
    | 'celestial'
    | 'hypogean'
    | 'dimensional'
    | 'draconis';

export type ClassCode = 'tank' | 'warrior' | 'ranger' | 'mage' | 'support';

export type TierCode = 'common' | 'legendary' | 'ascended';

export type HeroType = 'strength' | 'intelligence' | 'agility';

export interface Faction {
    id: number;
    code: FactionCode;
    name: string;
    name_pt: string;
    color_hex: string | null;
    display_order: number;
}

export interface HeroClass {
    id: number;
    code: ClassCode;
    name: string;
    name_pt: string;
    display_order: number;
}

export interface Tier {
    id: number;
    code: TierCode;
    name: string;
    name_pt: string;
    max_level: number;
    display_order: number;
}

export interface Hero {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    faction_id: number;
    hero_class_id: number;
    tier_id: number;
    gender: 'male' | 'female' | 'other' | null;
    type: HeroType | null;
    role_label: string | null;
    portrait_url: string | null;
    source_url: string | null;
    is_active: boolean;
    pending_review: boolean;
    is_awakened: boolean;
    lore?: string | null;
    abilities?: HeroAbility[];
    furniture_ability?: HeroFurnitureAbility | null;
    stats?: HeroStats | null;
    faction?: Faction;
    hero_class?: HeroClass;
    tier?: Tier;
    features?: HeroFeature[];
}

export interface HeroFurnitureAbility {
    id: number;
    hero_id: number;
    name: string;
    f3_description: string | null;
    f9_description: string | null;
}

export interface Beast {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    strength_buff: number;
    intelligence_buff: number;
    agility_buff: number;
    tier: string | null;
    tier_note: string | null;
    portrait_url: string | null;
    source_url: string | null;
    is_active: boolean;
}

export type HeroRatingMode = 'overall' | 'pvp' | 'pve' | 'labyrinth' | 'twisted_realm' | 'temporal_rift' | 'abyssal_expedition';
export type HeroRatingStage = 'early' | 'mid' | 'end' | 'all';
export type HeroRatingTier = 'S+' | 'S' | 'A+' | 'A' | 'B+' | 'B' | 'C+' | 'C' | 'D+' | 'D' | 'F';

export interface HeroRating {
    id: number;
    hero_id: number;
    mode: HeroRatingMode;
    stage: HeroRatingStage;
    tier: HeroRatingTier;
    source: string;
    source_url: string | null;
}

export type HeroEngravingLevel = 'e30' | 'e60';

export interface HeroEngraving {
    id: number;
    hero_id: number;
    level: HeroEngravingLevel;
    label: string;
    description: string;
    source: string;
    source_url: string | null;
}

export type HeroFeatureTag =
    | 'early_carry'
    | 'early_support'
    | 'early_tank'
    | 'early_utility'
    | 'pvp_top'
    | 'twisted_realm_top';

export interface HeroFeature {
    id: number;
    hero_id: number;
    tag: HeroFeatureTag;
    note: string | null;
    source_url?: string | null;
}

/** Componentes do score retornados por TeamScorer::score()->toArray(). */
export interface ScoreBreakdownPayload {
    faction_bonus: number;
    class_balance: number;
    synergy: number;
    power: number;
    tier_rating: number;
    feature_bonus: number;
    furniture_effects: number;
    total: number;
    details?: Record<string, unknown>;
}

export type TierListColor = 'rose' | 'orange' | 'amber' | 'emerald' | 'sky' | 'violet' | 'fuchsia' | 'zinc';

export interface UserTierListItem {
    id: number;
    user_tier_list_row_id: number;
    hero_id: number;
    position: number;
    hero?: Pick<Hero, 'id' | 'slug' | 'name' | 'faction_id' | 'hero_class_id' | 'portrait_url'>;
}

export interface UserTierListRow {
    id: number;
    user_tier_list_id: number;
    label: string;
    color: TierListColor;
    position: number;
    items?: UserTierListItem[];
}

export interface UserTierList {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    share_token: string | null;
    is_template?: boolean;
    cloned_from_id?: number | null;
    rows_count?: number;
    items_count?: number;
    rows?: UserTierListRow[];
    user?: { id: number; name: string };
    updated_at: string;
}

export type RecommendedFormationMode = 'pvp' | 'pve' | 'twisted_realm' | 'labyrinth' | 'king_tower' | 'arcane_labyrinth';

export interface RecommendedMatch {
    slug: string;
    name: string;
    mode: RecommendedFormationMode;
    tier: string | null;
    core_overlap: number;
    total_overlap: number;
    total_core: number;
    percent: number;
    missing_core: string[];
}

export interface RecommendedFormation {
    id: number;
    slug: string;
    name: string;
    mode: RecommendedFormationMode;
    tier: string | null;
    level_deficit: string | null;
    description: string | null;
    notes: string | null;
    hero_slugs: string[];
    core_count: number;
    artifacts: string[] | null;
    source_url: string | null;
    source_label: string | null;
}

export interface Collection {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    hp_bonus: number;
    attack_bonus: number;
    defense_bonus: number;
    portrait_url: string | null;
    source_url: string | null;
    is_active: boolean;
}

export type ArtifactKind = 'dura' | 'class';

export interface ArtifactAttribute {
    stat: string;
    value: string;
}

export interface ArtifactPower {
    stars: number;
    description: string;
}

export interface Artifact {
    id: number;
    slug: string;
    name: string;
    kind: ArtifactKind;
    hero_class_id: number | null;
    description: string | null;
    attributes: ArtifactAttribute[];
    powers: ArtifactPower[];
    obtained_in: string | null;
    portrait_url: string | null;
    max_stars: number;
}

export interface HeroStats {
    id: number;
    hero_id: number;
    hp: number | null;
    attack: number | null;
    defense: number | null;
    crit_rate: number | null;
    crit_amp: number | null;
    accuracy: number | null;
    dodge: number | null;
    haste: number | null;
    // Extras (Fase 24.8)
    vitality: number | null;
    armor: number | null;
    attack_speed: number | null;
    healing: number | null;
    recovery_per_second: number | null;
    energy_recovery_rate: number | null;
    crit_block_rate: number | null;
    crit_damage_resistance: number | null;
    physical_piercing: number | null;
    magic_piercing: number | null;
    physical_resistance: number | null;
    magic_resistance: number | null;
    magic_efficiency: number | null;
    magic_suppression: number | null;
    life_steal: number | null;
    tenacity: number | null;
    damage_resistance: number | null;
    perception: number | null;
    proficiency: number | null;
    dispel: number | null;
    erosion: number | null;
    aid: number | null;
    draconic_amplification: number | null;
    draconic_resistance: number | null;
    arena_damage_boost: number | null;
    arena_damage_reduction: number | null;
    trial_damage_boost: number | null;
}

export type HeroAbilityType = 'basic' | 'active' | 'passive' | 'ultimate' | 'signature' | 'awakened';

export interface HeroAbility {
    id: number;
    hero_id: number;
    name: string;
    type: HeroAbilityType;
    display_order: number;
    ascension_unlock: string | null;
    description: string | null;
    effects?: Record<string, unknown> | null;
}

export type EquipmentTier = 'T1' | 'T2' | 'T3' | 'T4' | 'T5' | 'T6';

export interface UserHero {
    id: number;
    hero_id: number;
    level: number;
    ascension_code: string | null;
    ascension_value: number;
    signature_item: number;
    furniture: number;
    engraving: number;
    weapon_tier: EquipmentTier | null;
    weapon_level: number | null;
    helmet_tier: EquipmentTier | null;
    helmet_level: number | null;
    chest_tier: EquipmentTier | null;
    chest_level: number | null;
    boots_tier: EquipmentTier | null;
    boots_level: number | null;
    artifact_id: number | null;
    artifact_star_level: number | null;
    artifact?: Artifact | null;
    collection_id: number | null;
    collection?: Collection | null;
    notes: string | null;
    power_rating?: number;
    // Stats reais do roster (cadastrados pelo user, opcional)
    hp?: number | null;
    attack?: number | null;
    defense?: number | null;
    crit_rate?: number | null;
    crit_amp?: number | null;
    accuracy?: number | null;
    dodge?: number | null;
    haste?: number | null;
    vitality?: number | null;
    armor?: number | null;
    attack_speed?: number | null;
    healing?: number | null;
    recovery_per_second?: number | null;
    energy_recovery_rate?: number | null;
    crit_block_rate?: number | null;
    crit_damage_resistance?: number | null;
    physical_piercing?: number | null;
    magic_piercing?: number | null;
    physical_resistance?: number | null;
    magic_resistance?: number | null;
    magic_efficiency?: number | null;
    magic_suppression?: number | null;
    life_steal?: number | null;
    tenacity?: number | null;
    damage_resistance?: number | null;
    perception?: number | null;
    proficiency?: number | null;
    dispel?: number | null;
    erosion?: number | null;
    aid?: number | null;
    draconic_amplification?: number | null;
    draconic_resistance?: number | null;
    arena_damage_boost?: number | null;
    arena_damage_reduction?: number | null;
    trial_damage_boost?: number | null;
}

export type RosterByHeroId = Record<number, UserHero>;

export type FormationMode = 'real' | 'target';

export interface ChallengeType {
    id: number;
    code: string;
    name: string;
    name_pt: string;
    description: string | null;
    min_teams: number;
    max_teams: number;
    is_pvp: boolean;
    teams_must_be_unique: boolean;
}

export interface FormationTeamHero {
    id: number;
    formation_team_id: number;
    hero_id: number;
    slot: number;
    row: 'front' | 'back' | null;
}

export interface FormationTeam {
    id: number;
    formation_id: number;
    position: number;
    name: string | null;
    score: number | null;
    team_heroes: FormationTeamHero[];
}

export interface Formation {
    id: number;
    user_id: number;
    challenge_type_id: number;
    name: string;
    mode: FormationMode;
    teams_count: number;
    notes: string | null;
    total_score: number | null;
    share_token: string | null;
    beast_id: number | null;
    beast?: Beast | null;
    created_at: string;
    updated_at: string;
    challenge_type?: ChallengeType;
    teams?: FormationTeam[];
}
