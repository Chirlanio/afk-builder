import type { FormDataConvertible } from '@inertiajs/core';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, ShieldCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface ResetPasswordProps {
    token: string;
    email: string;
}

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
    [key: string]: FormDataConvertible;
}

const inputClass =
    'border-amber-400/20 bg-black/40 text-amber-50 placeholder:text-amber-100/30 focus-visible:border-amber-400/60 focus-visible:ring-amber-400/30';

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm<ResetPasswordForm>({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Redefinir senha"
            description="Escolha uma nova senha para sua conta"
        >
            <Head title="Redefinir senha" />

            <form onSubmit={submit} className="flex flex-col gap-5">
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="email" className="text-amber-100/80">
                            E-mail
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autoComplete="email"
                            value={data.email}
                            readOnly
                            onChange={(e) => setData('email', e.target.value)}
                            className={`${inputClass} opacity-60`}
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password" className="text-amber-100/80">
                            Nova senha
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            autoComplete="new-password"
                            value={data.password}
                            autoFocus
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Mínimo 8 caracteres"
                            className={inputClass}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation" className="text-amber-100/80">
                            Confirme a nova senha
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            placeholder="Repita a nova senha"
                            className={inputClass}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-400 font-semibold text-zinc-900 shadow-[0_0_20px_-5px_rgba(251,191,36,0.6)] transition-all hover:from-amber-400 hover:to-yellow-300 hover:shadow-[0_0_25px_-5px_rgba(251,191,36,0.85)]"
                        disabled={processing}
                    >
                        {processing ? (
                            <LoaderCircle className="h-4 w-4 animate-spin" />
                        ) : (
                            <ShieldCheck className="h-4 w-4" />
                        )}
                        Redefinir senha
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
