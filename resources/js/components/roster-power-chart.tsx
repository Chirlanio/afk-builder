import { TrendingDown, TrendingUp } from 'lucide-react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface PowerHistoryPoint {
    date: string; // YYYY-MM-DD
    total_power: number;
    hero_count: number;
}

interface Props {
    history: PowerHistoryPoint[];
}

/**
 * LineChart com a evolução do power total do roster nos últimos snapshots.
 * Recharts é code-split — só carrega quando a Dashboard é montada.
 */
export function RosterPowerChart({ history }: Props) {
    if (history.length === 0) {
        return (
            <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                <h2 className="mb-2 text-sm font-semibold text-amber-100">Evolução do power</h2>
                <p className="text-xs text-amber-100/40">
                    Snapshots diários ainda não foram coletados. Rode{' '}
                    <code className="rounded bg-zinc-900 px-1 py-0.5 font-mono text-[10px]">php artisan roster:snapshot</code> ou aguarde 24h.
                </p>
            </div>
        );
    }

    const first = history[0];
    const last = history[history.length - 1];
    const delta = last.total_power - first.total_power;
    const deltaPct = first.total_power > 0 ? (delta / first.total_power) * 100 : 0;
    const isUp = delta >= 0;

    return (
        <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
            <header className="mb-4 flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold text-amber-100">Evolução do power</h2>
                    <p className="text-xs text-amber-100/50">
                        Últimos {history.length} snapshot{history.length === 1 ? '' : 's'} · começou em{' '}
                        <span className="font-mono">{first.total_power.toLocaleString('pt-BR')}</span> · agora{' '}
                        <span className="font-mono font-semibold text-amber-200">{last.total_power.toLocaleString('pt-BR')}</span>
                    </p>
                </div>
                <span
                    className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums ${
                        isUp ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300'
                    }`}
                >
                    {isUp ? <TrendingUp className="size-3" /> : <TrendingDown className="size-3" />}
                    {isUp && '+'}
                    {delta.toLocaleString('pt-BR')} ({deltaPct.toFixed(1)}%)
                </span>
            </header>
            <div className="h-56 w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={history} margin={{ top: 4, right: 10, left: 0, bottom: 4 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#27272a" />
                        <XAxis
                            dataKey="date"
                            stroke="#71717a"
                            tick={{ fontSize: 10 }}
                            tickFormatter={(v: string) => {
                                const d = new Date(`${v}T00:00:00`);
                                return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                            }}
                            minTickGap={20}
                        />
                        <YAxis stroke="#71717a" tick={{ fontSize: 10 }} tickFormatter={(v: number) => formatCompact(v)} width={50} />
                        <Tooltip
                            contentStyle={{
                                background: '#09090b',
                                border: '1px solid #3f3f46',
                                borderRadius: 8,
                                fontSize: 12,
                            }}
                            labelFormatter={(label) => {
                                if (typeof label !== 'string') return label;
                                return new Date(`${label}T00:00:00`).toLocaleDateString('pt-BR', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                });
                            }}
                            formatter={(value, name) => {
                                const num = typeof value === 'number' ? value : Number(value);
                                if (name === 'total_power') return [num.toLocaleString('pt-BR'), 'Power total'];
                                if (name === 'hero_count') return [num, 'Heroes'];
                                return [num, name];
                            }}
                        />
                        <Line type="monotone" dataKey="total_power" stroke="#fbbf24" strokeWidth={2} dot={false} activeDot={{ r: 4 }} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function formatCompact(n: number): string {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(0)}k`;
    return n.toString();
}
