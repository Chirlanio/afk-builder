import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { AlertTriangle, Loader2, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    formationId: number;
    currentCount: number;
    minTeams: number;
    maxTeams: number;
}

export function TeamsCountDialog({ open, onOpenChange, formationId, currentCount, minTeams, maxTeams }: Props) {
    const [value, setValue] = useState<number>(currentCount);
    const [saving, setSaving] = useState(false);
    const locked = minTeams === maxTeams;

    useEffect(() => {
        if (open) setValue(currentCount);
    }, [open, currentCount]);

    const clamp = (n: number) => Math.min(maxTeams, Math.max(minTeams, n));
    const isShrink = value < currentCount;
    const isUnchanged = value === currentCount;

    const submit = () => {
        if (isUnchanged || locked) return;
        setSaving(true);
        router.patch(
            `/formations/${formationId}/teams-count`,
            { teams_count: value },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => onOpenChange(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Users className="size-4" />
                        Quantidade de equipes
                    </DialogTitle>
                    <DialogDescription>
                        {locked
                            ? `Este desafio fixa em ${maxTeams} ${maxTeams === 1 ? 'equipe' : 'equipes'} — não dá pra alterar.`
                            : `Este desafio aceita de ${minTeams} a ${maxTeams} equipes. Atualmente: ${currentCount}.`}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2 py-2">
                    <Label htmlFor="teams_count_dialog">Nova quantidade</Label>
                    <Input
                        id="teams_count_dialog"
                        type="number"
                        min={minTeams}
                        max={maxTeams}
                        value={value}
                        disabled={locked}
                        onChange={(e) => setValue(clamp(Number(e.target.value) || minTeams))}
                        autoFocus
                    />

                    {isShrink && !locked && (
                        <div className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-300">
                            <AlertTriangle className="size-4 shrink-0" />
                            <div>
                                <strong>Atenção:</strong> reduzir vai apagar as equipes das posições {value + 1}–{currentCount} (heróis posicionados e
                                beasts). A versão atual será salva no histórico antes da mudança.
                            </div>
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={submit} disabled={saving || isUnchanged || locked} className="gap-1.5">
                        {saving && <Loader2 className="size-3.5 animate-spin" />}
                        Aplicar
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
