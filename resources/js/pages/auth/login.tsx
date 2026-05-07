import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Sparkles } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Entre na sua conta"
            description="Acesse seu roster e suas formações"
        >
            <Head title="Entrar" />

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="email" className="text-amber-100/80">
                            E-mail
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="seu@email.com"
                            className="border-amber-400/20 bg-black/40 text-amber-50 placeholder:text-amber-100/30 focus-visible:border-amber-400/60 focus-visible:ring-amber-400/30"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password" className="text-amber-100/80">
                                Senha
                            </Label>
                            {canResetPassword && (
                                <TextLink
                                    href={route('password.request')}
                                    className="ml-auto text-xs text-amber-300/80 hover:text-amber-200"
                                    tabIndex={5}
                                >
                                    Esqueci a senha
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            className="border-amber-400/20 bg-black/40 text-amber-50 placeholder:text-amber-100/30 focus-visible:border-amber-400/60 focus-visible:ring-amber-400/30"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="remember"
                            name="remember"
                            tabIndex={3}
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                            className="border-amber-400/40 data-[state=checked]:border-amber-400 data-[state=checked]:bg-amber-400 data-[state=checked]:text-zinc-900"
                        />
                        <Label htmlFor="remember" className="cursor-pointer text-sm text-amber-100/70">
                            Permanecer conectado
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-400 font-semibold text-zinc-900 shadow-[0_0_20px_-5px_rgba(251,191,36,0.6)] transition-all hover:from-amber-400 hover:to-yellow-300 hover:shadow-[0_0_25px_-5px_rgba(251,191,36,0.85)]"
                        tabIndex={4}
                        disabled={processing}
                    >
                        {processing ? (
                            <LoaderCircle className="h-4 w-4 animate-spin" />
                        ) : (
                            <Sparkles className="h-4 w-4" />
                        )}
                        Entrar
                    </Button>
                </div>

                <div className="text-center text-sm text-amber-100/60">
                    Ainda não tem conta?{' '}
                    <TextLink
                        href={route('register')}
                        tabIndex={5}
                        className="font-semibold text-amber-300 hover:text-amber-200"
                    >
                        Crie a sua
                    </TextLink>
                </div>
            </form>

            {status && (
                <div className="mt-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-center text-sm font-medium text-emerald-300">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
