import { TIER_LIST_COLOR_BG, TIER_LIST_COLOR_BORDER } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { Faction, Hero, UserTierList, UserTierListItem, UserTierListRow } from '@/types/afk';
import { Head } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';

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
            <div className="bg-background min-h-screen p-4 md:p-8">
                <div className="mx-auto max-w-5xl">
                    <header className="mb-6 flex items-start gap-3 border-b pb-4">
                        <ListChecks className="text-primary size-8 shrink-0" />
                        <div className="min-w-0 flex-1">
                            <h1 className="text-2xl leading-tight font-bold">{tierList.name}</h1>
                            <p className="text-muted-foreground text-xs">por {tierList.user.name}</p>
                            {tierList.description && <p className="text-muted-foreground mt-2 text-sm">{tierList.description}</p>}
                        </div>
                    </header>

                    <div className="flex flex-col gap-2">
                        {(tierList.rows as RichRow[]).map((row) => (
                            <div key={row.id} className={cn('flex overflow-hidden rounded-lg border-2', TIER_LIST_COLOR_BORDER[row.color])}>
                                <div
                                    className={cn('flex w-20 shrink-0 flex-col items-center justify-center px-2 py-3', TIER_LIST_COLOR_BG[row.color])}
                                >
                                    <span className="text-2xl leading-none font-black">{row.label}</span>
                                    <span className="text-[9px] tracking-wide uppercase opacity-80">{row.items.length}</span>
                                </div>
                                <div className="flex min-h-[80px] flex-1 flex-wrap items-start gap-1 p-2">
                                    {row.items.map((item) => {
                                        const hero = item.hero;
                                        return (
                                            <div
                                                key={item.id}
                                                className="bg-background flex w-14 flex-col items-center gap-0.5 rounded-md border p-1"
                                                title={hero?.name}
                                            >
                                                {hero?.portrait_url ? (
                                                    <img
                                                        src={hero.portrait_url}
                                                        alt={hero.name}
                                                        className="size-12 rounded object-cover object-top"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="bg-muted text-muted-foreground grid size-12 place-items-center rounded text-[10px]">
                                                        ?
                                                    </div>
                                                )}
                                                <span className="line-clamp-1 max-w-full text-center text-[9px] leading-none">
                                                    {hero?.name ?? '?'}
                                                </span>
                                            </div>
                                        );
                                    })}
                                    {row.items.length === 0 && <span className="text-muted-foreground self-center px-2 text-xs">—</span>}
                                </div>
                            </div>
                        ))}
                    </div>

                    <footer className="text-muted-foreground mt-8 text-center text-xs">
                        Compartilhado via{' '}
                        <a href="/" className="text-primary font-medium hover:underline">
                            AFK Builder
                        </a>
                    </footer>
                </div>
            </div>
        </>
    );
}
