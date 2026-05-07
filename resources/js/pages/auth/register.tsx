import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Sparkles } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

const inputClass =
    'border-amber-400/20 bg-black/40 text-amber-50 placeholder:text-amber-100/30 focus-visible:border-amber-400/60 focus-visible:ring-amber-400/30';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Crie sua conta"
            description="Comece a montar suas formações em segundos"
        >
            <Head title="Cadastrar" />
            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="name" className="text-amber-100/80">
                            Nome
                        </Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                            placeholder="Seu nome"
                            className={inputClass}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email" className="text-amber-100/80">
                            E-mail
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={2}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={processing}
                            placeholder="seu@email.com"
                            className={inputClass}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password" className="text-amber-100/80">
                            Senha
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={3}
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            disabled={processing}
                            placeholder="Mínimo 8 caracteres"
                            className={inputClass}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation" className="text-amber-100/80">
                            Confirme a senha
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            disabled={processing}
                            placeholder="Repita a senha"
                            className={inputClass}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-400 font-semibold text-zinc-900 shadow-[0_0_20px_-5px_rgba(251,191,36,0.6)] transition-all hover:from-amber-400 hover:to-yellow-300 hover:shadow-[0_0_25px_-5px_rgba(251,191,36,0.85)]"
                        tabIndex={5}
                        disabled={processing}
                    >
                        {processing ? (
                            <LoaderCircle className="h-4 w-4 animate-spin" />
                        ) : (
                            <Sparkles className="h-4 w-4" />
                        )}
                        Criar conta
                    </Button>
                </div>

                <div className="text-center text-sm text-amber-100/60">
                    Já tem conta?{' '}
                    <TextLink
                        href={route('login')}
                        tabIndex={6}
                        className="font-semibold text-amber-300 hover:text-amber-200"
                    >
                        Entrar
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
