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
    role_label: string | null;
    portrait_url: string | null;
    source_url: string | null;
    is_active: boolean;
    pending_review: boolean;
    is_awakened: boolean;
}

export interface UserHero {
    id: number;
    hero_id: number;
    level: number;
    ascension_code: string | null;
    ascension_value: number;
    signature_item: number;
    furniture: number;
    engraving: number;
    notes: string | null;
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
    created_at: string;
    updated_at: string;
    challenge_type?: ChallengeType;
    teams?: FormationTeam[];
}
