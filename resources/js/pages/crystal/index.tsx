import { ResourceEditorDialog, type UserResources } from '@/components/resource-editor-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, FactionCode, TierCode } from '@/types/afk';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, ChevronRight, Crown, Edit3, Gem, Hammer, Info, Loader2, Settings2, Sparkles, Users } from 'lucide-react';
import { useCallback, useState } from 'react';

interface PentagramHero {
    hero_id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    faction_code: FactionCode;
    class_code: ClassCode;
    tier_code: TierCode;
    level: number;
    ascension_value: number;
}

interface CrystalState {
    level: number;
    strengthen_progress: number;
    strengthens_per_level: number;
    slots_unlocked: number;
    effective_level: number;
    is_upgraded: boolean;
    level_cap: number;
    can_upgrade: boolean;
    max_absolute: number;
}

interface NextLevelCost {
    level: number;
    gold_cost: number;
    hero_exp_cost: number;
    dust_cost: number;
}

interface StrengthenCost {
    gold: number;
    hero_exp: number;
    hero_essence: number;
    is_last_in_cycle: boolean;
    next_level: number;
}

interface PageProps {
    crystal: CrystalState;
    pentagram: PentagramHero[];
    nextLevelCost: NextLevelCost | null;
    strengthenCost: StrengthenCost | null;
    resources: UserResources;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cristal Ressonante', href: '/crystal' }];

function formatNumber(n: number): string {
    return n.toLocaleString('pt-BR');
}

function formatCompact(n: number): string {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(0)}k`;
    return n.toString();
}

export default function CristalIndex({ crystal, pentagram, nextLevelCost, strengthenCost, resources }: PageProps) {
    const [currentResources, setCurrentResources] = useState<UserResources>(resources);
    const [resourceDialogOpen, setResourceDialogOpen] = useState(false);
    const [syncDialogOpen, setSyncDialogOpen] = useState(false);
    const [syncForm, setSyncForm] = useState({
        level: crystal.level,
        strengthen_progress: crystal.strengthen_progress,
        slots_unlocked: crystal.slots_unlocked,
    });
    const [syncing, setSyncing] = useState(false);
    const [strengthening, setStrengthening] = useState(false);
    const [strengthenError, setStrengthenError] = useState<string | null>(null);

    const onResourcesSaved = useCallback((updated: UserResources) => {
        setCurrentResources(updated);
        router.reload({ only: ['crystal', 'resources', 'strengthenCost'] });
    }, []);

    const handleSync = async () => {
        setSyncing(true);
        try {
            await axios.patch('/crystal', syncForm);
            router.reload({ only: ['crystal', 'strengthenCost'] });
            setSyncDialogOpen(false);
        } catch (err) {
            console.error('Falha ao sincronizar cristal:', err);
        } finally {
            setSyncing(false);
        }
    };

    const handleStrengthen = async () => {
        setStrengthening(true);
        setStrengthenError(null);
        try {
            await axios.post('/crystal/strengthen');
            router.reload({ only: ['crystal', 'resources', 'strengthenCost', 'nextLevelCost'] });
        } catch (err: unknown) {
            const e = err as { response?: { data?: { error?: string } } };
            const code = e.response?.data?.error;
            if (code === 'insufficient_resources') {
                setStrengthenError('Recursos insuficientes pra esse fortalecimento.');
            } else if (code === 'level_cap_reached') {
                setStrengthenError('Cristal já atingiu o teto destravado pelos seus heróis Ascendidos.');
            } else {
                setStrengthenError('Falha ao fortalecer.');
            }
        } finally {
            setStrengthening(false);
        }
    };

    const progressPct = crystal.is_upgraded ? (crystal.strengthen_progress / crystal.strengthens_per_level) * 100 : 0;

    const fifthHighestLevel = pentagram[4]?.level ?? 0;
    const allTop5At240 = pentagram.length === 5 && fifthHighestLevel >= 240;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cristal Ressonante" />

            <div className="flex flex-col gap-4 p-1">
                {/* Header explicativo */}
                <header className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <div className="flex items-start gap-3">
                        <Gem className="mt-0.5 size-5 text-amber-300" />
                        <div className="flex-1">
                            <h1 className="text-lg font-semibold text-amber-100">Cristal Ressonante</h1>
                            <p className="mt-1 text-xs text-amber-100/60">
                                Acompanhe o nível do cristal, seu pentagrama (top 5 do roster) e o custo de fortalecer. Ver{' '}
                                <Link href="/guides/cristal-ressonante" className="underline hover:text-amber-200">
                                    guia
                                </Link>{' '}
                                pra detalhes.
                            </p>
                        </div>
                    </div>
                </header>

                {/* Status do cristal */}
                <section className="grid gap-3 lg:grid-cols-3">
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-4">
                        <div className="mb-2 flex items-center gap-1.5 text-[10px] tracking-wider text-amber-100/50 uppercase">
                            <Gem className="size-3.5" />
                            Nível efetivo
                        </div>
                        <div className="font-mono text-2xl font-bold text-amber-100">{crystal.effective_level}</div>
                        <div className="mt-0.5 text-[10px] text-amber-100/40">
                            {crystal.is_upgraded
                                ? 'Cristal upgradado — controle direto'
                                : allTop5At240
                                  ? 'Pronto pra destravar upgrade no jogo'
                                  : '5º maior do pentagrama'}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-4">
                        <div className="mb-2 flex items-center gap-1.5 text-[10px] tracking-wider text-amber-100/50 uppercase">
                            <Crown className="size-3.5" />
                            Teto destravado
                        </div>
                        <div className="font-mono text-2xl font-bold text-amber-100">
                            {crystal.level_cap}
                            <span className="ml-1 text-xs font-normal text-amber-100/40">/ {crystal.max_absolute}</span>
                        </div>
                        <div className="mt-0.5 text-[10px] text-amber-100/40">240 + 5 × heros Ascendidos (sem Dimensionais)</div>
                    </div>

                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-4">
                        <div className="mb-2 flex items-center gap-1.5 text-[10px] tracking-wider text-amber-100/50 uppercase">
                            <Users className="size-3.5" />
                            Slots desbloqueados
                        </div>
                        <div className="font-mono text-2xl font-bold text-amber-100">{crystal.slots_unlocked}</div>
                        <div className="mt-0.5 text-[10px] text-amber-100/40">5 iniciais (pentagrama) + Essências Revigorantes</div>
                    </div>
                </section>

                {/* Pentagrama: top 5 do roster */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <header className="mb-3 flex items-center gap-2">
                        <Sparkles className="size-4 text-amber-300" />
                        <h2 className="text-sm font-semibold text-amber-100">Pentagrama (top 5 do roster)</h2>
                        <span className="ml-auto text-[10px] text-amber-100/40">
                            {pentagram.length < 5 && `Apenas ${pentagram.length} hero(s) no roster`}
                        </span>
                    </header>

                    {pentagram.length === 0 ? (
                        <p className="text-xs text-amber-100/50">Roster vazio — adicione heróis para ver o pentagrama.</p>
                    ) : (
                        <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                            {pentagram.map((h, idx) => (
                                <li
                                    key={h.hero_id}
                                    className={cn(
                                        'flex flex-col gap-1 rounded-lg border border-amber-400/10 bg-black/30 p-2',
                                        idx === 4 && 'border-amber-400/40 ring-1 ring-amber-400/30',
                                    )}
                                >
                                    <div className="flex items-center gap-2">
                                        <span className="grid size-5 flex-shrink-0 place-items-center rounded-full bg-amber-400/15 font-mono text-[9px] font-bold text-amber-200">
                                            {idx + 1}
                                        </span>
                                        {h.portrait_url ? (
                                            <img
                                                src={h.portrait_url}
                                                alt={h.name}
                                                loading="lazy"
                                                className={cn(
                                                    'size-9 rounded-md object-cover object-top',
                                                    FACTION_THEME[h.faction_code]?.cardGradient,
                                                )}
                                            />
                                        ) : (
                                            <div className="size-9 rounded-md bg-zinc-800" />
                                        )}
                                        <div className="flex min-w-0 flex-col">
                                            <span className="truncate text-xs font-medium text-amber-100">{h.name}</span>
                                            <span className="text-[10px] text-amber-100/50">{CLASS_LABEL[h.class_code]}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between text-[10px]">
                                        <span className="font-mono font-semibold text-amber-200">Nv {h.level}</span>
                                        {h.tier_code === 'ascended' && (
                                            <span className={cn('rounded px-1 py-0.5 text-[9px]', TIER_THEME.ascended?.badge)}>Ascended</span>
                                        )}
                                    </div>
                                    {idx === 4 && <span className="text-[9px] text-amber-300/80">↑ Define nível compartilhado</span>}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>

                {/* Upgrade / fortalecimento (só pós-upgrade) */}
                {crystal.is_upgraded ? (
                    <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <header className="mb-3 flex flex-wrap items-center gap-2">
                            <Hammer className="size-4 text-amber-300" />
                            <h2 className="text-sm font-semibold text-amber-100">Fortalecer cristal</h2>
                            {!crystal.can_upgrade && (
                                <span className="ml-auto rounded-full bg-rose-500/15 px-2 py-0.5 text-[10px] text-rose-300">
                                    No teto destravado — ascenda mais heroes
                                </span>
                            )}
                        </header>

                        {crystal.can_upgrade && strengthenCost && nextLevelCost ? (
                            <>
                                <div className="mb-3 flex items-center gap-3">
                                    <span className="text-xs text-amber-100/70">
                                        Nível {crystal.level} → {strengthenCost.next_level}
                                    </span>
                                    <div className="flex-1 overflow-hidden rounded-full bg-zinc-800">
                                        <div
                                            className="h-2 rounded-full bg-gradient-to-r from-amber-500 to-rose-500 transition-all"
                                            style={{ width: `${progressPct}%` }}
                                        />
                                    </div>
                                    <span className="font-mono text-xs text-amber-200 tabular-nums">
                                        {crystal.strengthen_progress}/{crystal.strengthens_per_level}
                                    </span>
                                </div>

                                <div className="grid gap-2 text-xs sm:grid-cols-3">
                                    <div className="rounded-md border border-amber-400/15 bg-black/20 p-2">
                                        <div className="text-[10px] text-amber-100/40 uppercase">Ouro / fortalecer</div>
                                        <div
                                            className={cn(
                                                'font-mono font-bold',
                                                currentResources.gold >= strengthenCost.gold ? 'text-emerald-300' : 'text-rose-300',
                                            )}
                                        >
                                            {formatCompact(strengthenCost.gold)}
                                            <span className="ml-1 text-[10px] text-amber-100/40">/ {formatCompact(currentResources.gold)}</span>
                                        </div>
                                    </div>
                                    <div className="rounded-md border border-amber-400/15 bg-black/20 p-2">
                                        <div className="text-[10px] text-amber-100/40 uppercase">EXP / fortalecer</div>
                                        <div
                                            className={cn(
                                                'font-mono font-bold',
                                                currentResources.hero_exp >= strengthenCost.hero_exp ? 'text-emerald-300' : 'text-rose-300',
                                            )}
                                        >
                                            {formatCompact(strengthenCost.hero_exp)}
                                            <span className="ml-1 text-[10px] text-amber-100/40">/ {formatCompact(currentResources.hero_exp)}</span>
                                        </div>
                                    </div>
                                    <div className="rounded-md border border-amber-400/15 bg-black/20 p-2">
                                        <div className="text-[10px] text-amber-100/40 uppercase">
                                            Essência {strengthenCost.is_last_in_cycle ? '(último!)' : '(0)'}
                                        </div>
                                        <div
                                            className={cn(
                                                'font-mono font-bold',
                                                currentResources.hero_essence >= strengthenCost.hero_essence ? 'text-emerald-300' : 'text-rose-300',
                                            )}
                                        >
                                            {formatNumber(strengthenCost.hero_essence)}
                                            <span className="ml-1 text-[10px] text-amber-100/40">
                                                / {formatNumber(currentResources.hero_essence)}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-3 text-[10px] text-amber-100/40">
                                    Custo total do ciclo (10 fortalecimentos): {formatNumber(nextLevelCost.gold_cost)} ouro ·{' '}
                                    {formatNumber(nextLevelCost.hero_exp_cost)} EXP · {formatNumber(nextLevelCost.dust_cost)} essência
                                </div>

                                {strengthenError && (
                                    <div className="mt-2 flex items-center gap-1.5 rounded-md border border-rose-500/40 bg-rose-500/10 px-2 py-1 text-[11px] text-rose-300">
                                        <AlertTriangle className="size-3" />
                                        {strengthenError}
                                    </div>
                                )}

                                <div className="mt-3 flex justify-end">
                                    <Button onClick={handleStrengthen} disabled={strengthening} className="gap-1.5">
                                        {strengthening ? <Loader2 className="size-3.5 animate-spin" /> : <Hammer className="size-3.5" />}
                                        Fortalecer ×1
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <p className="text-xs text-amber-100/60">
                                {!crystal.can_upgrade
                                    ? 'Cristal já está no teto destravado pelos seus heroes Ascendidos. Ascenda mais heroes pra aumentar o limite.'
                                    : 'Sem dados de custo pro próximo nível.'}
                            </p>
                        )}
                    </section>
                ) : (
                    <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <header className="mb-2 flex items-center gap-2">
                            <Info className="size-4 text-amber-300" />
                            <h2 className="text-sm font-semibold text-amber-100">Modo Pentagrama (pré-upgrade)</h2>
                        </header>
                        <p className="text-xs text-amber-100/60">
                            O cristal usa o nível do 5º herói mais alto ({pentagram[4]?.level ?? '—'}) como referência. Quando seus 5 melhores heroes
                            chegarem ao nível 240, você poderá destravar o upgrade no jogo e usar esta página pra controlar o nível do cristal
                            manualmente.
                        </p>
                    </section>
                )}

                {/* Recursos */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs">
                            <span className="flex items-center gap-1">
                                <span className="text-amber-100/50">Ouro:</span>
                                <span className="font-mono font-semibold text-amber-100 tabular-nums">{formatCompact(currentResources.gold)}</span>
                            </span>
                            <span className="flex items-center gap-1">
                                <span className="text-amber-100/50">EXP:</span>
                                <span className="font-mono font-semibold text-amber-100 tabular-nums">
                                    {formatCompact(currentResources.hero_exp)}
                                </span>
                            </span>
                            <span className="flex items-center gap-1">
                                <span className="text-amber-100/50">Essência:</span>
                                <span className="font-mono font-semibold text-amber-100 tabular-nums">
                                    {formatNumber(currentResources.hero_essence)}
                                </span>
                            </span>
                            <span className="flex items-center gap-1">
                                <span className="text-amber-100/50">Essências Revig.:</span>
                                <span className="font-mono font-semibold text-amber-100 tabular-nums">
                                    {formatNumber(currentResources.revigorating_essences)}
                                </span>
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={() => setSyncDialogOpen(true)} className="gap-1.5">
                                <Edit3 className="size-3.5" />
                                Sincronizar com o jogo
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => setResourceDialogOpen(true)} className="gap-1.5">
                                <Settings2 className="size-3.5" />
                                Editar recursos
                            </Button>
                        </div>
                    </div>
                </section>

                <p className="text-center text-[10px] text-amber-100/40">
                    Custos baseados na tabela oficial (240–501). O 10º fortalecimento de cada nível também consome Essência de Herói.
                </p>
            </div>

            {/* Dialog de sync com o jogo */}
            <Dialog open={syncDialogOpen} onOpenChange={setSyncDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Sincronizar com o jogo</DialogTitle>
                        <DialogDescription>
                            Ajuste o nível atual, o progresso de fortalecimento e a quantidade de slots conforme o estado do seu cristal no jogo.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-3 py-2">
                        <div className="flex flex-col gap-1">
                            <label className="text-xs font-medium text-amber-100">Nível do cristal (240–{crystal.max_absolute})</label>
                            <Input
                                type="number"
                                min={240}
                                max={crystal.max_absolute}
                                value={syncForm.level}
                                onChange={(e) => setSyncForm((p) => ({ ...p, level: Number(e.target.value) }))}
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="text-xs font-medium text-amber-100">Fortalecimentos no nível atual (0–9)</label>
                            <Input
                                type="number"
                                min={0}
                                max={9}
                                value={syncForm.strengthen_progress}
                                onChange={(e) => setSyncForm((p) => ({ ...p, strengthen_progress: Number(e.target.value) }))}
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <label className="text-xs font-medium text-amber-100">Slots desbloqueados (5+)</label>
                            <Input
                                type="number"
                                min={5}
                                max={60}
                                value={syncForm.slots_unlocked}
                                onChange={(e) => setSyncForm((p) => ({ ...p, slots_unlocked: Number(e.target.value) }))}
                            />
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setSyncDialogOpen(false)} disabled={syncing}>
                            Cancelar
                        </Button>
                        <Button onClick={handleSync} disabled={syncing} className="gap-1.5">
                            {syncing ? <Loader2 className="size-3.5 animate-spin" /> : <ChevronRight className="size-3.5" />}
                            Salvar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ResourceEditorDialog
                open={resourceDialogOpen}
                onOpenChange={setResourceDialogOpen}
                initial={currentResources}
                onSaved={onResourcesSaved}
            />
        </AppLayout>
    );
}
