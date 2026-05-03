import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Hero, HeroStats } from '@/types/afk';

interface PageProps {
    hero: Hero;
    stats: HeroStats | null;
}

interface StatsForm {
    hp: string;
    attack: string;
    defense: string;
    crit_rate: string;
    crit_amp: string;
    accuracy: string;
    dodge: string;
    haste: string;
    [key: string]: string;
}

const FIELDS: { key: keyof Omit<StatsForm, never>; label: string; max: number; help?: string }[] = [
    { key: 'hp', label: 'HP', max: 999_999, help: 'Pontos de vida base (Nv 1).' },
    { key: 'attack', label: 'Ataque', max: 999_999, help: 'Atributo de ataque base (Nv 1).' },
    { key: 'defense', label: 'Defesa', max: 999_999, help: 'Atributo de defesa base (Nv 1).' },
    { key: 'crit_rate', label: 'Taxa Crítica', max: 9_999, help: 'Em pontos (ex: 50 = 5%).' },
    { key: 'crit_amp', label: 'Amp. Crítica', max: 9_999, help: 'Amplificação de dano crítico (em pontos).' },
    { key: 'accuracy', label: 'Precisão', max: 9_999, help: 'Hit Rating (em pontos).' },
    { key: 'dodge', label: 'Esquiva', max: 9_999, help: 'Dodge Rating (em pontos).' },
    { key: 'haste', label: 'Pressa', max: 9_999, help: 'Haste / velocidade de energia (em pontos).' },
];

export default function HeroStatsForm({ hero, stats }: PageProps) {
    const init = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

    const { data, setData, patch, processing, errors } = useForm<StatsForm>({
        hp: init(stats?.hp),
        attack: init(stats?.attack),
        defense: init(stats?.defense),
        crit_rate: init(stats?.crit_rate),
        crit_amp: init(stats?.crit_amp),
        accuracy: init(stats?.accuracy),
        dodge: init(stats?.dodge),
        haste: init(stats?.haste),
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Heróis', href: '/heroes' },
        { title: hero.name, href: `/heroes/${hero.slug}` },
        { title: 'Editar stats', href: `/admin/heroes/${hero.slug}/stats` },
    ];

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        patch(`/admin/heroes/${hero.slug}/stats`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Stats — ${hero.name}`} />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Link href={`/heroes/${hero.slug}`} className="text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="text-2xl font-semibold">
                        Stats de <span className="font-bold">{hero.name}</span>
                    </h1>
                </div>
                <p className="text-sm text-muted-foreground">
                    Cadastre os atributos <strong>base</strong> do herói (Nv 1, sem ascensão, móveis, assinatura ou inscrições). Campos vazios serão tratados como não cadastrados — se todos forem deixados em branco, o registro é apagado.
                </p>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4 rounded-xl border bg-card p-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {FIELDS.map(({ key, label, max, help }) => (
                            <div key={key} className="flex flex-col gap-1.5">
                                <Label htmlFor={key}>{label}</Label>
                                <Input
                                    id={key}
                                    type="number"
                                    min={0}
                                    max={max}
                                    inputMode="numeric"
                                    value={data[key]}
                                    onChange={(e) => setData(key, e.target.value)}
                                    placeholder="—"
                                />
                                {help && <p className="text-xs text-muted-foreground">{help}</p>}
                                {errors[key] && <p className="text-xs text-destructive">{errors[key]}</p>}
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Link href={`/heroes/${hero.slug}`}>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Save className="size-4" />
                            {processing ? 'Salvando...' : 'Salvar stats'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
