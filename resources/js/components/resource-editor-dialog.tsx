import axios from 'axios';
import { Loader2, Save, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

export interface UserResources {
    primordial_emblems: number;
    amplifying_emblems: number;
    faction_emblems: number;
    mythic_furniture: number;
    engraving_scrolls: number;
}

interface FieldMeta {
    key: keyof UserResources;
    label: string;
    hint: string;
}

const FIELDS: FieldMeta[] = [
    { key: 'primordial_emblems', label: 'Emblemas Primordiais', hint: 'Para Assinatura +1 a +20' },
    { key: 'amplifying_emblems', label: 'Emblemas Amplificantes', hint: 'Para Assinatura +21 a +30' },
    { key: 'faction_emblems', label: 'Emblemas de Facção', hint: 'Para Assinatura +31 a +40 (Celestial/Hypogean)' },
    { key: 'mythic_furniture', label: 'Móveis Míticos', hint: 'Frags pra subir F0→F9 (pool agregado)' },
    { key: 'engraving_scrolls', label: 'Pergaminhos de Inscrição', hint: 'Pra subir engravings (pool agregado)' },
];

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initial: UserResources;
    onSaved: (resources: UserResources) => void;
}

export function ResourceEditorDialog({ open, onOpenChange, initial, onSaved }: Props) {
    const [values, setValues] = useState<UserResources>(initial);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const handleChange = (key: keyof UserResources, raw: string) => {
        const sanitized = raw.replace(/[^\d]/g, '');
        const parsed = sanitized === '' ? 0 : Math.min(99_999_999, Math.max(0, Number(sanitized)));
        setValues((prev) => ({ ...prev, [key]: parsed }));
    };

    const save = async () => {
        setSaving(true);
        setError(null);
        try {
            const { data } = await axios.patch<{ resources: UserResources }>('/roster/resources', values);
            onSaved(data.resources);
            onOpenChange(false);
        } catch (err: unknown) {
            const e = err as { response?: { data?: { message?: string } } };
            setError(e.response?.data?.message ?? 'Falha ao salvar.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Recursos disponíveis</DialogTitle>
                    <DialogDescription>
                        Informe o que você tem em estoque. O Planner destaca as sugestões que cabem no orçamento atual.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-3 py-2">
                    {FIELDS.map(({ key, label, hint }) => (
                        <div key={key} className="flex flex-col gap-1">
                            <label className="flex items-center justify-between text-xs font-medium text-amber-100">
                                {label}
                                <span className="text-[10px] text-amber-100/40">{hint}</span>
                            </label>
                            <Input
                                type="text"
                                inputMode="numeric"
                                value={values[key] === 0 ? '' : String(values[key])}
                                onChange={(e) => handleChange(key, e.target.value)}
                                placeholder="0"
                                className="text-right tabular-nums"
                            />
                        </div>
                    ))}
                </div>

                {error && <p className="text-xs text-rose-300">{error}</p>}

                <DialogFooter className="gap-2">
                    <Button variant="ghost" onClick={() => onOpenChange(false)} disabled={saving}>
                        <X className="size-3.5" />
                        Cancelar
                    </Button>
                    <Button onClick={save} disabled={saving} className="gap-1.5">
                        {saving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                        Salvar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
