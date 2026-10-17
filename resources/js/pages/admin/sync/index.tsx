import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Ban,
    BookOpen,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    CircleAlert,
    Dumbbell,
    Image as ImageIcon,
    Languages,
    Loader2,
    PawPrint,
    Play,
    RefreshCw,
    Sofa,
    Sparkles,
    Terminal,
    Trophy,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type CommandStatus = 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';

interface CommandOption {
    name: string;
    type: 'bool' | 'int' | 'string' | 'enum';
    label: string;
    placeholder: string | null;
    options: Record<string, string> | null;
    default: string | null;
    min: number | null;
    max: number | null;
}

interface CommandSpec {
    signature: string;
    label: string;
    description: string | null;
    icon: string;
    options: CommandOption[];
}

interface CommandRun {
    id: number;
    command: string;
    args: Record<string, unknown>;
    status: CommandStatus;
    started_at: string | null;
    finished_at: string | null;
    duration_seconds: number | null;
    exit_code: number | null;
    user_name: string | null;
    created_at: string | null;
}

interface PageProps {
    commands: Record<string, CommandSpec>;
    runs: CommandRun[];
    queueDriver: string;
}

const ICON_MAP: Record<string, LucideIcon> = {
    Users,
    Image: ImageIcon,
    BookOpen,
    Sofa,
    Dumbbell,
    Sparkles,
    PawPrint,
    Languages,
    Trophy,
    Terminal,
};

const STATUS_LABEL: Record<CommandStatus, string> = {
    pending: 'Aguardando',
    running: 'Executando',
    completed: 'Concluído',
    failed: 'Falhou',
    cancelled: 'Cancelado',
};

const STATUS_BADGE: Record<CommandStatus, string> = {
    pending: 'border-amber-400/40 bg-amber-400/10 text-amber-300',
    running: 'border-sky-400/40 bg-sky-400/10 text-sky-300',
    completed: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-300',
    failed: 'border-red-400/40 bg-red-400/10 text-red-300',
    cancelled: 'border-zinc-400/40 bg-zinc-400/10 text-zinc-300',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Sincronizações', href: '/admin/sync' }];

export default function SyncAdminIndex({ commands, runs: initialRuns, queueDriver }: PageProps) {
    const [runs, setRuns] = useState<CommandRun[]>(initialRuns);
    const [openRunId, setOpenRunId] = useState<number | null>(null);
    const [openRunDetail, setOpenRunDetail] = useState<(CommandRun & { output: string }) | null>(null);
    const [loadingDetail, setLoadingDetail] = useState(false);
    const [cancellingId, setCancellingId] = useState<number | null>(null);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const hasActive = useMemo(() => runs.some((r) => r.status === 'pending' || r.status === 'running'), [runs]);

    const cancelRun = async (run: CommandRun) => {
        const ok = await confirm({
            title: `Cancelar execução de "${run.command}"?`,
            description: 'O job é removido da fila antes do worker pegar. Se já tiver pegado, a execução continua.',
            confirmLabel: 'Cancelar execução',
            variant: 'destructive',
        });
        if (!ok) return;

        setCancellingId(run.id);
        try {
            await axios.delete(`/admin/sync/runs/${run.id}`);
            setRuns((prev) => prev.map((r) => (r.id === run.id ? { ...r, status: 'cancelled', finished_at: new Date().toISOString() } : r)));
        } catch (e) {
            const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
            const msg =
                err.response?.data?.message ??
                Object.values(err.response?.data?.errors ?? {})
                    .flat()
                    .join(' · ') ??
                'Erro ao cancelar';
            alert(msg);
            // Refetch real status pra UI ficar consistente.
            axios
                .get('/admin/sync/runs')
                .then((r) => setRuns(r.data.runs))
                .catch(() => {});
        } finally {
            setCancellingId(null);
        }
    };

    useEffect(() => {
        if (!hasActive) return;
        const id = setInterval(() => {
            axios
                .get('/admin/sync/runs')
                .then((r) => setRuns(r.data.runs))
                .catch(() => {});
        }, 3000);
        return () => clearInterval(id);
    }, [hasActive]);

    useEffect(() => {
        if (openRunId === null) {
            setOpenRunDetail(null);
            return;
        }
        setLoadingDetail(true);
        axios
            .get(`/admin/sync/runs/${openRunId}`)
            .then((r) => setOpenRunDetail(r.data.run))
            .finally(() => setLoadingDetail(false));
    }, [openRunId]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {confirmDialog}
            <Head title="Sincronizações" />

            <PageShell
                title="Sincronizações"
                icon={RefreshCw}
                description="Dispare comandos artisan de sincronização da Fandom Wiki, tradução EN→PT-BR e enrichment de catálogo. Cada execução roda em background — acompanhe o status na tabela abaixo."
            >
                {queueDriver !== 'database' && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-amber-200">
                        <CircleAlert className="size-5 shrink-0" />
                        <div className="text-sm">
                            <p className="font-semibold">
                                Queue driver: <code className="rounded bg-black/30 px-1">{queueDriver}</code>
                            </p>
                            <p className="text-amber-200/80">
                                Esta funcionalidade espera <code>QUEUE_CONNECTION=database</code>. Confirme se o worker está rodando:{' '}
                                <code className="ml-1 rounded bg-black/30 px-1">php artisan queue:work</code>.
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-sm text-amber-100/80">
                    <p>
                        <span className="font-semibold text-amber-200">Importante:</span> rode{' '}
                        <code className="rounded bg-black/30 px-1.5 py-0.5">php artisan queue:work</code> num terminal separado para que os jobs sejam
                        processados. Sem o worker, as execuções ficam em <em>Aguardando</em> indefinidamente.
                    </p>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wider text-amber-100/60 uppercase">Comandos disponíveis</h2>
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {Object.values(commands).map((cmd) => (
                            <CommandCard key={cmd.signature} cmd={cmd} onDispatched={(run) => setRuns((prev) => [run, ...prev])} />
                        ))}
                    </div>
                </section>

                <section className="space-y-3">
                    <h2 className="flex items-center gap-2 text-sm font-semibold tracking-wider text-amber-100/60 uppercase">
                        Últimas execuções
                        {hasActive && (
                            <span className="inline-flex items-center gap-1 text-[10px] font-medium text-sky-300 normal-case">
                                <Loader2 className="size-3 animate-spin" />
                                atualizando
                            </span>
                        )}
                    </h2>
                    {runs.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-amber-400/20 bg-black/20 p-8 text-center text-sm text-amber-100/50">
                            Nenhuma execução registrada ainda.
                        </div>
                    ) : (
                        <div className="bg-card overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[700px] text-sm">
                                <thead className="text-muted-foreground border-b text-xs">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">Comando</th>
                                        <th className="px-3 py-2 text-left font-medium">Status</th>
                                        <th className="px-3 py-2 text-left font-medium">Iniciado</th>
                                        <th className="px-3 py-2 text-left font-medium">Duração</th>
                                        <th className="px-3 py-2 text-left font-medium">Exec.</th>
                                        <th className="px-3 py-2 text-right font-medium"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {runs.map((run) => (
                                        <tr key={run.id} className="hover:bg-muted/30 border-b last:border-0">
                                            <td className="px-3 py-2">
                                                <div className="font-mono text-xs">{run.command}</div>
                                                {Object.keys(run.args).length > 0 && (
                                                    <div className="text-muted-foreground mt-0.5 truncate text-[10px]">
                                                        {Object.entries(run.args)
                                                            .map(([k, v]) => `${k}=${v === true ? '✓' : v}`)
                                                            .join(' · ')}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium',
                                                        STATUS_BADGE[run.status],
                                                    )}
                                                >
                                                    {run.status === 'running' && <Loader2 className="size-2.5 animate-spin" />}
                                                    {run.status === 'completed' && <CheckCircle2 className="size-2.5" />}
                                                    {run.status === 'failed' && <CircleAlert className="size-2.5" />}
                                                    {STATUS_LABEL[run.status]}
                                                </span>
                                            </td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs whitespace-nowrap">
                                                {run.started_at ? new Date(run.started_at).toLocaleTimeString('pt-BR') : '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs tabular-nums">
                                                {run.duration_seconds !== null ? `${run.duration_seconds}s` : '—'}
                                            </td>
                                            <td className="text-muted-foreground px-3 py-2 text-xs">{run.user_name ?? '—'}</td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="flex justify-end gap-1">
                                                    {run.status === 'pending' && (
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => cancelRun(run)}
                                                            disabled={cancellingId === run.id}
                                                            className="text-zinc-400 hover:bg-red-500/10 hover:text-red-300"
                                                        >
                                                            {cancellingId === run.id ? (
                                                                <Loader2 className="size-3.5 animate-spin" />
                                                            ) : (
                                                                <Ban className="size-3.5" />
                                                            )}
                                                            Cancelar
                                                        </Button>
                                                    )}
                                                    <Button size="sm" variant="ghost" onClick={() => setOpenRunId(run.id)}>
                                                        Ver
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </PageShell>

            <Dialog open={openRunId !== null} onOpenChange={(o) => !o && setOpenRunId(null)}>
                <DialogContent className="max-w-3xl">
                    <DialogHeader>
                        <DialogTitle className="font-mono text-base">{openRunDetail?.command ?? '—'}</DialogTitle>
                        <DialogDescription asChild>
                            <div>
                                {openRunDetail && (
                                    <div className="flex flex-wrap items-center gap-2 text-xs">
                                        <Badge variant="outline" className={STATUS_BADGE[openRunDetail.status]}>
                                            {STATUS_LABEL[openRunDetail.status]}
                                        </Badge>
                                        {openRunDetail.exit_code !== null && (
                                            <span className="text-muted-foreground">exit={openRunDetail.exit_code}</span>
                                        )}
                                        {openRunDetail.duration_seconds !== null && (
                                            <span className="text-muted-foreground">{openRunDetail.duration_seconds}s</span>
                                        )}
                                        {openRunDetail.user_name && <span className="text-muted-foreground">por {openRunDetail.user_name}</span>}
                                    </div>
                                )}
                            </div>
                        </DialogDescription>
                    </DialogHeader>

                    {loadingDetail ? (
                        <div className="flex justify-center py-8">
                            <Loader2 className="text-muted-foreground size-6 animate-spin" />
                        </div>
                    ) : openRunDetail ? (
                        <pre className="max-h-[60vh] overflow-auto rounded-md bg-black/60 p-3 text-[11px] leading-relaxed whitespace-pre-wrap text-emerald-200">
                            {openRunDetail.output || '(sem output)'}
                        </pre>
                    ) : null}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );

    function CommandCard({ cmd, onDispatched }: { cmd: CommandSpec; onDispatched: (run: CommandRun) => void }) {
        const [open, setOpen] = useState(false);
        const [busy, setBusy] = useState(false);
        const [args, setArgs] = useState<Record<string, unknown>>(() => {
            const init: Record<string, unknown> = {};
            for (const opt of cmd.options) {
                if (opt.default !== null) init[opt.name] = opt.default;
            }
            return init;
        });
        const [error, setError] = useState<string | null>(null);

        const Icon = ICON_MAP[cmd.icon] ?? Terminal;

        const dispatch = async () => {
            setBusy(true);
            setError(null);
            try {
                const cleanedArgs: Record<string, unknown> = {};
                for (const [k, v] of Object.entries(args)) {
                    if (v === '' || v === null || v === undefined || v === false) continue;
                    cleanedArgs[k] = v;
                }
                const res = await axios.post('/admin/sync', {
                    command: cmd.signature,
                    args: cleanedArgs,
                });
                onDispatched(res.data.run);
                setOpen(false);
            } catch (e) {
                const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
                const msg =
                    err.response?.data?.message ??
                    Object.values(err.response?.data?.errors ?? {})
                        .flat()
                        .join(' · ') ??
                    'Erro ao disparar comando';
                setError(msg);
            } finally {
                setBusy(false);
            }
        };

        return (
            <div className="bg-card flex flex-col gap-3 rounded-xl border border-amber-400/15 p-4">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-amber-500/15 text-amber-300">
                        <Icon className="size-5" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <h3 className="text-sm font-semibold text-amber-50">{cmd.label}</h3>
                        <p className="font-mono text-[10px] text-amber-100/40">{cmd.signature}</p>
                    </div>
                </div>

                {cmd.description && <p className="text-xs leading-relaxed text-amber-100/60">{cmd.description}</p>}

                {cmd.options.length > 0 && (
                    <button
                        type="button"
                        onClick={() => setOpen((o) => !o)}
                        className="inline-flex items-center gap-1 self-start text-xs font-medium text-amber-300 hover:text-amber-200"
                    >
                        {open ? <ChevronUp className="size-3" /> : <ChevronDown className="size-3" />}
                        {open ? 'Ocultar opções' : `Opções (${cmd.options.length})`}
                    </button>
                )}

                {open && cmd.options.length > 0 && (
                    <div className="space-y-3 rounded-md border border-amber-400/10 bg-black/20 p-3">
                        {cmd.options.map((opt) => (
                            <div key={opt.name} className="space-y-1">
                                <Label className="text-xs">{opt.label}</Label>
                                {opt.type === 'bool' && (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            checked={Boolean(args[opt.name])}
                                            onCheckedChange={(v) => setArgs((a) => ({ ...a, [opt.name]: v === true }))}
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            <code>--{opt.name}</code>
                                        </span>
                                    </div>
                                )}
                                {opt.type === 'int' && (
                                    <Input
                                        type="number"
                                        min={opt.min ?? undefined}
                                        max={opt.max ?? undefined}
                                        placeholder={opt.placeholder ?? undefined}
                                        value={(args[opt.name] as string) ?? ''}
                                        onChange={(e) => setArgs((a) => ({ ...a, [opt.name]: e.target.value }))}
                                        className="h-8 text-xs"
                                    />
                                )}
                                {opt.type === 'string' && (
                                    <Input
                                        placeholder={opt.placeholder ?? undefined}
                                        value={(args[opt.name] as string) ?? ''}
                                        onChange={(e) => setArgs((a) => ({ ...a, [opt.name]: e.target.value }))}
                                        className="h-8 text-xs"
                                    />
                                )}
                                {opt.type === 'enum' && opt.options && (
                                    <Select
                                        value={(args[opt.name] as string) ?? opt.default ?? ''}
                                        onValueChange={(v) => setArgs((a) => ({ ...a, [opt.name]: v }))}
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.entries(opt.options).map(([k, label]) => (
                                                <SelectItem key={k} value={k}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {error && <p className="rounded border border-red-500/40 bg-red-500/10 px-2 py-1 text-xs text-red-300">{error}</p>}

                <Button onClick={dispatch} disabled={busy} size="sm" className="self-start">
                    {busy ? <Loader2 className="size-3.5 animate-spin" /> : <Play className="size-3.5" />}
                    Executar
                </Button>
            </div>
        );
    }
}
