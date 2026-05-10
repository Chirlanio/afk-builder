import { Head, useForm } from '@inertiajs/react';
import { ListChecks, Save } from 'lucide-react';
import { type FormEvent } from 'react';
import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tier Lists', href: '/tier-lists' },
    { title: 'Nova', href: '/tier-lists/create' },
];

export default function TierListCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/tier-lists');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nova Tier List" />

            <PageShell title="Nova Tier List" icon={ListChecks} description="Crie uma tier list. Após criar, você poderá arrastar heróis e customizar os tiers.">
                <form onSubmit={submit} className="flex max-w-2xl flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="name">Nome *</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="Ex: Meta PvP atual"
                            required
                            maxLength={100}
                        />
                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="description">Descrição (opcional)</Label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            className="min-h-[80px] rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows={3}
                            maxLength={5000}
                            placeholder="Contexto, critério, etc..."
                        />
                        {errors.description && <p className="text-xs text-destructive">{errors.description}</p>}
                    </div>

                    <p className="text-xs text-muted-foreground">
                        Tiers padrão: <strong>S+</strong>, <strong>S</strong>, <strong>A</strong>, <strong>B</strong>, <strong>C</strong>. Você pode adicionar/remover/renomear depois.
                    </p>

                    <div className="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" asChild>
                            <a href="/tier-lists">Cancelar</a>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="size-4" />
                            {processing ? 'Criando...' : 'Criar'}
                        </Button>
                    </div>
                </form>
            </PageShell>
        </AppLayout>
    );
}
