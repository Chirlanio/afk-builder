import { Head } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { TIER_LIST_COLOR_BG, TIER_LIST_COLOR_BORDER } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { Faction, Hero, UserTierList, UserTierListItem, UserTierListRow } from '@/types/afk';

type RichRow = UserTierListRow & { items: (UserTierListItem & { hero: Hero })[] };

interface PageProps {
    tierList: UserTierList & {
        rows: RichRow[];
        user: { id: number; name: string };
    };
    factions: Faction[];
}

export default function TierListPublic({ tierList }: PageProps) {
    return (
        <>
            <Head title={`${tierList.name} — Tier List`} />
            <div className="min-h-screen bg-background p-4 md:p-8">
                <div className="mx-auto max-w-5xl">
                    <header className="mb-6 flex items-start gap-3 border-b pb-4">
                        <ListChecks className="size-8 shrink-0 text-primary" />
                        <div className="min-w-0 flex-1">
                            <h1 className="text-2xl font-bold leading-tight">{tierList.name}</h1>
                            <p className="text-xs text-muted-foreground">por {tierList.user.name}</p>
                            {tierList.description && (
                                <p className="mt-2 text-sm text-muted-foreground">{tierList.description}</p>
                            )}
                        </div>
                    </header>

                    <div className="flex flex-col gap-2">
                        {(tierList.rows as RichRow[]).map((row) => (
                            <div key={row.id} className={cn('flex overflow-hidden rounded-lg border-2', TIER_LIST_COLOR_BORDER[row.color])}>
                                <div className={cn('flex w-20 shrink-0 flex-col items-center justify-center px-2 py-3', TIER_LIST_COLOR_BG[row.color])}>
                                    <span className="text-2xl font-black leading-none">{row.label}</span>
                                    <span className="text-[9px] uppercase tracking-wide opacity-80">{row.items.length}</span>
                                </div>
                                <div className="flex flex-1 flex-wrap items-start gap-1 p-2 min-h-[80px]">
                                    {row.items.map((item) => {
                                        const hero = item.hero;
                                        return (
                                            <div key={item.id} className="flex w-14 flex-col items-center gap-0.5 rounded-md border bg-background p-1" title={hero?.name}>
                                                {hero?.portrait_url ? (
                                                    <img src={hero.portrait_url} alt={hero.name} className="size-12 rounded object-cover object-top" loading="lazy" />
                                                ) : (
                                                    <div className="grid size-12 place-items-center rounded bg-muted text-[10px] text-muted-foreground">?</div>
                                                )}
                                                <span className="line-clamp-1 max-w-full text-center text-[9px] leading-none">{hero?.name ?? '?'}</span>
                                            </div>
                                        );
                                    })}
                                    {row.items.length === 0 && (
                                        <span className="self-center px-2 text-xs text-muted-foreground">—</span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>

                    <footer className="mt-8 text-center text-xs text-muted-foreground">
                        Compartilhado via <a href="/" className="font-medium text-primary hover:underline">AFK Builder</a>
                    </footer>
                </div>
            </div>
        </>
    );
}
