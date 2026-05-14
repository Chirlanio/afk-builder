import { forwardRef } from 'react';
import { CLASS_ICON, TIER_THEME } from '@/lib/afk';
import type {
    ChallengeType,
    ClassCode,
    Faction,
    FactionCode,
    Formation,
    FormationTeam,
    Hero,
    HeroClass,
    Tier,
    TierCode,
} from '@/types/afk';

// Mapa local de cores RGB por facção — html-to-image não inlina @apply Tailwind
// pra gradients, então cores precisam ser hex/rgb explícitas.
const FACTION_COLOR_HEX: Record<FactionCode, string> = {
    lightbearer: '#fbbf24',
    mauler: '#f97316',
    wilder: '#10b981',
    graveborn: '#a855f7',
    celestial: '#7dd3fc',
    hypogean: '#ef4444',
    dimensional: '#ec4899',
    draconis: '#06b6d4',
};

export interface ExportTeamScorePayload {
    total: number;
    faction_bonus?: number;
    synergy?: number;
}

interface Props {
    formation: Formation & {
        challenge_type: ChallengeType;
        teams: FormationTeam[];
        tags?: Array<{ id: number; name: string; color: string }>;
    };
    heroById: Record<number, Hero>;
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
    scoresByTeamPosition?: Record<number, ExportTeamScorePayload | null>;
}

/**
 * Card retangular dedicado pra export PNG via html-to-image.
 * SEMPRE renderizado off-screen (fixed + invisible) — não é UI interativa.
 * Dimensões fixas (1080×variável) e tipografia robusta pra screenshot.
 */
export const FormationExportPreview = forwardRef<HTMLDivElement, Props>(function FormationExportPreview(
    { formation, heroById, factionById, classById, tierById, scoresByTeamPosition },
    ref,
) {
    return (
        <div
            ref={ref}
            // Fora da viewport: fixed, top muito alto, sem afetar layout. Não pode usar
            // display:none porque html-to-image precisa medir/renderizar.
            style={{
                position: 'fixed',
                top: '-99999px',
                left: '0',
                width: '1080px',
                pointerEvents: 'none',
            }}
            data-export-preview
        >
            <div
                style={{
                    background: 'linear-gradient(135deg, #0a0613 0%, #1a0e2e 50%, #0a0613 100%)',
                    padding: '32px',
                    color: '#f5f5f5',
                    fontFamily: 'system-ui, -apple-system, "Segoe UI", sans-serif',
                }}
            >
                {/* Header */}
                <div style={{ borderBottom: '1px solid rgba(251, 191, 36, 0.2)', paddingBottom: '16px', marginBottom: '20px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <h1
                            style={{
                                fontSize: '32px',
                                fontWeight: 700,
                                margin: 0,
                                color: '#fcd34d',
                                lineHeight: 1.1,
                            }}
                        >
                            {formation.name}
                        </h1>
                        <span style={{ marginLeft: 'auto', fontSize: '14px', color: '#9ca3af' }}>
                            afk-builder
                        </span>
                    </div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '10px', alignItems: 'center' }}>
                        <BadgeExport label={formation.challenge_type.name_pt} tone="primary" />
                        {formation.mode === 'target' && <BadgeExport label="Modo Alvo" tone="muted" />}
                        {formation.challenge_type.is_pvp && <BadgeExport label="PvP" tone="rose" />}
                        <BadgeExport
                            label={`${formation.teams_count} ${formation.teams_count === 1 ? 'equipe' : 'equipes'}`}
                            tone="muted"
                        />
                        {formation.tags?.map((tag) => (
                            <BadgeExport key={tag.id} label={tag.name} tone="amber" />
                        ))}
                    </div>
                </div>

                {/* Teams */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    {formation.teams.map((team) => {
                        const heroes = team.team_heroes
                            .slice()
                            .sort((a, b) => a.slot - b.slot)
                            .map((th) => heroById[th.hero_id])
                            .filter((h): h is Hero => Boolean(h));

                        const score = scoresByTeamPosition?.[team.position] ?? null;

                        return (
                            <div
                                key={team.position}
                                style={{
                                    background: 'rgba(0, 0, 0, 0.35)',
                                    border: '1px solid rgba(251, 191, 36, 0.15)',
                                    borderRadius: '14px',
                                    padding: '14px 16px',
                                }}
                            >
                                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '12px' }}>
                                    <span
                                        style={{
                                            background: 'rgba(251, 191, 36, 0.18)',
                                            color: '#fcd34d',
                                            fontWeight: 700,
                                            fontSize: '14px',
                                            width: '28px',
                                            height: '28px',
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            borderRadius: '8px',
                                        }}
                                    >
                                        {team.position}
                                    </span>
                                    <span style={{ fontSize: '16px', fontWeight: 600, color: '#f5f5f5' }}>
                                        {team.name ?? `Equipe ${team.position}`}
                                    </span>
                                    {score && (
                                        <span style={{ marginLeft: 'auto', fontSize: '13px', color: '#9ca3af', fontFamily: 'monospace' }}>
                                            Score: <span style={{ color: '#fcd34d', fontWeight: 700 }}>{score.total.toFixed(2)}</span>
                                        </span>
                                    )}
                                </div>
                                <div style={{ display: 'flex', gap: '10px' }}>
                                    {Array.from({ length: 5 }, (_, i) => {
                                        const hero = heroes[i];
                                        if (!hero) {
                                            return (
                                                <EmptySlot key={i} />
                                            );
                                        }
                                        const faction = factionById[hero.faction_id];
                                        const klass = classById[hero.hero_class_id];
                                        const tier = tierById[hero.tier_id];
                                        return (
                                            <HeroSlot
                                                key={hero.id}
                                                hero={hero}
                                                factionCode={faction.code as FactionCode}
                                                classCode={klass.code as ClassCode}
                                                tierCode={tier.code as TierCode}
                                            />
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Footer */}
                <div
                    style={{
                        marginTop: '20px',
                        paddingTop: '12px',
                        borderTop: '1px solid rgba(251, 191, 36, 0.1)',
                        display: 'flex',
                        justifyContent: 'space-between',
                        fontSize: '11px',
                        color: '#6b7280',
                    }}
                >
                    <span>Gerado em {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' })}</span>
                    <span>github.com/Chirlanio/afk-builder</span>
                </div>
            </div>
        </div>
    );
});

function BadgeExport({ label, tone }: { label: string; tone: 'primary' | 'muted' | 'rose' | 'amber' }) {
    const styles: Record<string, { bg: string; color: string; border: string }> = {
        primary: { bg: 'rgba(251, 191, 36, 0.18)', color: '#fcd34d', border: 'rgba(251, 191, 36, 0.4)' },
        muted: { bg: 'rgba(156, 163, 175, 0.15)', color: '#d1d5db', border: 'rgba(156, 163, 175, 0.3)' },
        rose: { bg: 'rgba(244, 63, 94, 0.15)', color: '#fda4af', border: 'rgba(244, 63, 94, 0.4)' },
        amber: { bg: 'rgba(251, 191, 36, 0.12)', color: '#fbbf24', border: 'rgba(251, 191, 36, 0.3)' },
    };
    const s = styles[tone];
    return (
        <span
            style={{
                fontSize: '12px',
                fontWeight: 500,
                padding: '4px 10px',
                borderRadius: '999px',
                background: s.bg,
                color: s.color,
                border: `1px solid ${s.border}`,
            }}
        >
            {label}
        </span>
    );
}

function EmptySlot() {
    return (
        <div
            style={{
                width: '180px',
                height: '180px',
                borderRadius: '12px',
                border: '2px dashed rgba(156, 163, 175, 0.2)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '11px',
                color: '#6b7280',
            }}
        >
            vazio
        </div>
    );
}

function HeroSlot({
    hero,
    factionCode,
    classCode,
    tierCode,
}: {
    hero: Hero;
    factionCode: FactionCode;
    classCode: ClassCode;
    tierCode: TierCode;
}) {
    const tierTheme = TIER_THEME[tierCode];
    const ClassIcon = CLASS_ICON[classCode];
    const factionColor = FACTION_COLOR_HEX[factionCode];

    return (
        <div
            style={{
                width: '180px',
                borderRadius: '12px',
                background: `linear-gradient(180deg, ${factionColor}26 0%, rgba(0,0,0,0.5) 100%)`,
                border: `2px solid ${factionColor}80`,
                overflow: 'hidden',
                display: 'flex',
                flexDirection: 'column',
                position: 'relative',
            }}
        >
            {/* Tier badge */}
            <div
                style={{
                    position: 'absolute',
                    top: '6px',
                    left: '6px',
                    background: tierBgFromTheme(tierTheme),
                    color: '#fff',
                    fontSize: '9px',
                    fontWeight: 700,
                    padding: '2px 6px',
                    borderRadius: '4px',
                    textTransform: 'uppercase',
                    letterSpacing: '0.05em',
                    zIndex: 2,
                }}
            >
                {tierTheme.label.slice(0, 3)}
            </div>
            {/* Class icon */}
            <div
                style={{
                    position: 'absolute',
                    top: '6px',
                    right: '6px',
                    background: 'rgba(0, 0, 0, 0.7)',
                    padding: '3px',
                    borderRadius: '999px',
                    zIndex: 2,
                    width: '22px',
                    height: '22px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: factionColor,
                }}
            >
                <ClassIcon style={{ width: '14px', height: '14px' }} />
            </div>
            {/* Portrait */}
            <div
                style={{
                    height: '140px',
                    display: 'flex',
                    alignItems: 'flex-end',
                    justifyContent: 'center',
                    overflow: 'hidden',
                }}
            >
                {hero.portrait_url && (
                    <img
                        src={hero.portrait_url}
                        alt={hero.name}
                        crossOrigin="anonymous"
                        style={{
                            width: '100%',
                            height: '100%',
                            objectFit: 'contain',
                            objectPosition: 'bottom',
                        }}
                    />
                )}
            </div>
            {/* Name */}
            <div
                style={{
                    background: 'rgba(0, 0, 0, 0.65)',
                    padding: '6px 8px',
                    fontSize: '12px',
                    fontWeight: 600,
                    textAlign: 'center',
                    color: '#f5f5f5',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    whiteSpace: 'nowrap',
                }}
            >
                {hero.name}
            </div>
        </div>
    );
}

function tierBgFromTheme(theme: typeof TIER_THEME[TierCode]): string {
    // Mapeia o badge Tailwind para gradient inline já que html-to-image precisa
    // de CSS computado em RGB — algumas variants Tailwind via @apply podem falhar.
    if (theme.label === 'Ascended') return 'linear-gradient(90deg, #fb923c, #facc15)';
    if (theme.label === 'Legendary') return '#a855f7';
    if (theme.label === 'Common' || theme.label === 'Rare') return '#3b82f6';
    return '#52525b';
}

