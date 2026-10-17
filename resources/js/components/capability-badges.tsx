import { AlertTriangle, Ban } from 'lucide-react';

import {
    CAPABILITIES,
    CAPABILITY_GROUP_CLASS,
    capabilityMeta,
    TEAM_KIND_LABEL,
    type CapabilityTag,
    type TeamCapabilityAnalysis,
} from '@/lib/capabilities';
import { cn } from '@/lib/utils';

export function CapabilityBadge({ tag, strength, className }: { tag: string; strength?: number; className?: string }) {
    const meta = capabilityMeta(tag);
    if (!meta) return null;

    return (
        <span
            title={meta.description}
            className={cn(
                'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] leading-none font-medium',
                CAPABILITY_GROUP_CLASS[meta.group],
                className,
            )}
        >
            {meta.label}
            {strength !== undefined && strength > 1 && <span className="opacity-70">×{strength}</span>}
        </span>
    );
}

/** Perfil de um herói: {tag: força}. Ordem estável da taxonomia. */
export function CapabilityList({ profile, className }: { profile: Record<string, number> | null | undefined; className?: string }) {
    const tags = (Object.keys(CAPABILITIES) as CapabilityTag[]).filter((tag) => profile?.[tag]);
    if (tags.length === 0) return null;

    return (
        <div className={cn('flex flex-wrap gap-1', className)}>
            {tags.map((tag) => (
                <CapabilityBadge key={tag} tag={tag} strength={profile?.[tag]} />
            ))}
        </div>
    );
}

/**
 * O que o time cobre e o que falta. Informativo: não entra no score (ver
 * TeamCapabilityAnalyzer no backend).
 */
export function TeamCapabilityPanel({ analysis, className }: { analysis: TeamCapabilityAnalysis | null | undefined; className?: string }) {
    if (!analysis?.has_profiles) return null;

    const covered = (Object.keys(CAPABILITIES) as CapabilityTag[]).filter((tag) => analysis.covered[tag]);
    const ineffective = Object.keys(analysis.ineffective) as CapabilityTag[];

    return (
        <div className={cn('space-y-2 px-3 py-2 text-xs', className)}>
            <div className="text-muted-foreground flex items-center justify-between text-[10px] font-semibold tracking-wider uppercase">
                <span>Capacidades do time</span>
                <span className="font-normal normal-case">contexto: {TEAM_KIND_LABEL[analysis.kind]} · informativo</span>
            </div>

            {covered.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {covered.map((tag) => (
                        <CapabilityBadge key={tag} tag={tag} strength={analysis.covered[tag]?.length} />
                    ))}
                </div>
            )}

            {(analysis.missing.length > 0 || analysis.no_sustain) && (
                <div className="flex items-start gap-1.5 text-amber-300/90">
                    <AlertTriangle className="mt-0.5 size-3 shrink-0" />
                    <span>
                        {analysis.no_sustain && 'Sem cura, escudo ou sobrevivência. '}
                        {analysis.missing.length > 0 && <>Falta: {analysis.missing.map((tag) => capabilityMeta(tag)?.label).join(', ')}.</>}
                    </span>
                </div>
            )}

            {ineffective.length > 0 && (
                <div className="flex items-start gap-1.5 text-rose-300/90">
                    <Ban className="mt-0.5 size-3 shrink-0" />
                    <span>
                        Sem efeito contra chefe: {ineffective.map((tag) => capabilityMeta(tag)?.label).join(', ')} (
                        {[...new Set(ineffective.flatMap((tag) => analysis.ineffective[tag] ?? []))].join(', ')}).
                    </span>
                </div>
            )}
        </div>
    );
}
