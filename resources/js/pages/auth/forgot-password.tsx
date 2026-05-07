import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <AuthLayout
            title="Esqueci minha senha"
            description="Informe seu e-mail e enviaremos um link de redefinição"
        >
            <Head title="Recuperar senha" />

            {status && (
                <div className="mb-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-center text-sm font-medium text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
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
                        autoFocus
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="seu@email.com"
                        className="border-amber-400/20 bg-black/40 text-amber-50 placeholder:text-amber-100/30 focus-visible:border-amber-400/60 focus-visible:ring-amber-400/30"
                    />
                    <InputError message={errors.email} />
                </div>

                <Button
                    type="submit"
                    className="w-full bg-gradient-to-r from-amber-500 via-amber-400 to-yellow-400 font-semibold text-zinc-900 shadow-[0_0_20px_-5px_rgba(251,191,36,0.6)] transition-all hover:from-amber-400 hover:to-yellow-300 hover:shadow-[0_0_25px_-5px_rgba(251,191,36,0.85)]"
                    disabled={processing}
                >
                    {processing ? (
                        <LoaderCircle className="h-4 w-4 animate-spin" />
                    ) : (
                        <Mail className="h-4 w-4" />
                    )}
                    Enviar link de redefinição
                </Button>

                <div className="text-center text-sm text-amber-100/60">
                    <TextLink
                        href={route('login')}
                        className="font-semibold text-amber-300 hover:text-amber-200"
                    >
                        Voltar para o login
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
