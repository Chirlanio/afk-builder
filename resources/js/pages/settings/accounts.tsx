import { Head, router, useForm } from '@inertiajs/react';
import { Star, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

interface HeroLite {
    id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
}

interface AccountRow {
    id: number;
    name: string;
    server: string | null;
    is_default: boolean;
    avatar: HeroLite | null;
}

interface Props {
    accounts: AccountRow[];
    heroes: HeroLite[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Contas', href: '/settings/accounts' }];

export default function AccountsSettings({ accounts, heroes }: Props) {
    const { confirm, dialog } = useConfirm();

    const handleDelete = async (id: number) => {
        const ok = await confirm({
            title: 'Remover conta?',
            description:
                'Todos os dados vinculados (roster, formações, tier lists, recursos) serão apagados permanentemente. Esta ação não pode ser desfeita.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/settings/accounts/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contas" />

            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall
                        title="Contas de jogo"
                        description={`Você tem ${accounts.length} conta(s). Cada uma tem seu próprio roster, formações e cristal ressonante.`}
                    />

                    <div className="space-y-3">
                        {accounts.map((account) => (
                            <AccountCard
                                key={account.id}
                                account={account}
                                heroes={heroes}
                                canDelete={accounts.length > 1}
                                onDelete={() => handleDelete(account.id)}
                            />
                        ))}
                    </div>

                    <div className="rounded-lg border p-4">
                        <HeadingSmall title="Nova conta" description="Adicione uma segunda conta do jogo (ex: alt, guilda X, servidor diferente)." />
                        <CreateForm heroes={heroes} />
                    </div>
                </div>

                {dialog}
            </SettingsLayout>
        </AppLayout>
    );
}

function AccountCard({
    account,
    heroes,
    canDelete,
    onDelete,
}: {
    account: AccountRow;
    heroes: HeroLite[];
    canDelete: boolean;
    onDelete: () => void;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        name: account.name,
        server: account.server ?? '',
        avatar_hero_id: account.avatar?.id ?? '',
        is_default: account.is_default,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/settings/accounts/${account.id}`, {
            onSuccess: () => setEditing(false),
            preserveScroll: true,
        });
    };

    const promoteDefault = () => {
        patch(`/settings/accounts/${account.id}`, {
            data: { ...data, is_default: true },
            preserveScroll: true,
        } as never);
    };

    if (editing) {
        return (
            <form onSubmit={submit} className="space-y-3 rounded-lg border p-4">
                <div className="grid gap-2">
                    <Label htmlFor={`name-${account.id}`}>Nome</Label>
                    <Input id={`name-${account.id}`} value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor={`server-${account.id}`}>Servidor (opcional)</Label>
                    <Input
                        id={`server-${account.id}`}
                        value={data.server}
                        onChange={(e) => setData('server', e.target.value)}
                        placeholder="Ex: S123, Global"
                    />
                </div>
                <HeroPicker
                    heroes={heroes}
                    value={data.avatar_hero_id ? Number(data.avatar_hero_id) : null}
                    onChange={(v) => setData('avatar_hero_id', v ?? '')}
                />
                <div className="flex gap-2">
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => {
                            reset();
                            setEditing(false);
                        }}
                    >
                        Cancelar
                    </Button>
                </div>
            </form>
        );
    }

    return (
        <div className="flex items-center gap-3 rounded-lg border p-3">
            <div className="bg-muted flex size-10 items-center justify-center overflow-hidden rounded-md">
                {account.avatar?.portrait_url ? (
                    <img src={account.avatar.portrait_url} alt="" className="size-full object-cover" />
                ) : (
                    <span className="text-muted-foreground text-xs">?</span>
                )}
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="truncate font-medium">{account.name}</span>
                    {account.is_default && (
                        <span className="inline-flex items-center gap-1 rounded-md bg-amber-500/20 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                            <Star className="size-3" /> Padrão
                        </span>
                    )}
                </div>
                {account.server && <span className="text-muted-foreground text-xs">{account.server}</span>}
            </div>
            <div className="flex gap-1">
                {!account.is_default && (
                    <Button size="sm" variant="ghost" onClick={promoteDefault} title="Definir como padrão">
                        <Star className="size-4" />
                    </Button>
                )}
                <Button size="sm" variant="ghost" onClick={() => setEditing(true)}>
                    Editar
                </Button>
                {canDelete && (
                    <Button size="sm" variant="ghost" onClick={onDelete} title="Remover">
                        <Trash2 className="text-destructive size-4" />
                    </Button>
                )}
            </div>
        </div>
    );
}

function CreateForm({ heroes }: { heroes: HeroLite[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        server: '',
        avatar_hero_id: '' as string | number,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/settings/accounts', {
            onSuccess: () => reset(),
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="mt-4 space-y-3">
            <div className="grid gap-2">
                <Label htmlFor="new-name">Nome</Label>
                <Input
                    id="new-name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="Ex: Alt, Guild X, Global"
                    required
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="new-server">Servidor (opcional)</Label>
                <Input id="new-server" value={data.server} onChange={(e) => setData('server', e.target.value)} placeholder="Ex: S123" />
            </div>
            <HeroPicker
                heroes={heroes}
                value={data.avatar_hero_id ? Number(data.avatar_hero_id) : null}
                onChange={(v) => setData('avatar_hero_id', v ?? '')}
            />
            <Button type="submit" disabled={processing}>
                Criar conta
            </Button>
        </form>
    );
}

function HeroPicker({ heroes, value, onChange }: { heroes: HeroLite[]; value: number | null; onChange: (v: number | null) => void }) {
    const [search, setSearch] = useState('');
    const filtered = search ? heroes.filter((h) => h.name.toLowerCase().includes(search.toLowerCase())) : heroes.slice(0, 12);
    const selected = heroes.find((h) => h.id === value);

    return (
        <div className="grid gap-2">
            <Label>Avatar (opcional)</Label>
            {selected ? (
                <div className="flex items-center gap-2 rounded-md border p-2">
                    <img src={selected.portrait_url ?? ''} alt="" className="size-8 rounded object-cover" />
                    <span className="flex-1 text-sm">{selected.name}</span>
                    <Button size="sm" variant="ghost" onClick={() => onChange(null)}>
                        Remover
                    </Button>
                </div>
            ) : (
                <>
                    <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar herói..." />
                    <div className="grid max-h-40 grid-cols-6 gap-1 overflow-y-auto">
                        {filtered.map((hero) => (
                            <button
                                key={hero.id}
                                type="button"
                                onClick={() => {
                                    onChange(hero.id);
                                    setSearch('');
                                }}
                                className="hover:ring-primary relative aspect-square overflow-hidden rounded border hover:ring-2"
                                title={hero.name}
                            >
                                {hero.portrait_url && <img src={hero.portrait_url} alt={hero.name} className="size-full object-cover" />}
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
