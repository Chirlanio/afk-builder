import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface AuthAfkLayoutProps {
    children: ReactNode;
    title?: string;
    description?: string;
}

export default function AuthAfkLayout({ children, title, description }: AuthAfkLayoutProps) {
    return (
        <div className="relative min-h-svh w-full overflow-hidden bg-[#0a0613] text-amber-50">
            <div
                className="pointer-events-none absolute inset-0 -z-10 opacity-40 blur-3xl"
                style={{
                    backgroundImage:
                        'radial-gradient(circle at 20% 20%, rgba(251,191,36,0.20), transparent 55%), radial-gradient(circle at 80% 80%, rgba(168,85,247,0.25), transparent 55%), radial-gradient(circle at 50% 0%, rgba(244,63,94,0.18), transparent 60%)',
                }}
            />

            <div className="relative grid min-h-svh grid-cols-1 lg:grid-cols-[1.1fr_1fr]">
                <aside className="relative hidden overflow-hidden lg:block">
                    <img
                        src="/images/auth/athalia-teaser.png"
                        alt="AFK Arena"
                        className="absolute inset-0 size-full object-cover object-top opacity-90"
                        loading="eager"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-[#0a0613] via-[#0a0613]/40 to-transparent" />
                    <div className="absolute inset-0 bg-gradient-to-r from-transparent via-transparent to-[#0a0613]" />

                    <div className="relative z-10 flex h-full flex-col justify-between p-10 xl:p-14">
                        <Link href="/" className="group flex items-center gap-3">
                            <img
                                src="/images/auth/afk-arena-logo.png"
                                alt="AFK Arena"
                                className="h-12 w-auto drop-shadow-[0_0_18px_rgba(251,191,36,0.45)] transition-transform group-hover:scale-105"
                            />
                            <span className="hidden text-sm font-semibold tracking-[0.3em] text-amber-200/80 uppercase xl:inline">Builder</span>
                        </Link>

                        <div className="max-w-md space-y-5">
                            <span className="inline-flex items-center gap-2 rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-medium tracking-widest text-amber-200 uppercase">
                                <span className="size-1.5 rounded-full bg-amber-300 shadow-[0_0_8px_rgba(251,191,36,0.9)]" />
                                Esperia aguarda
                            </span>
                            <h2 className="text-3xl leading-tight font-bold text-amber-50 xl:text-4xl">Domine cada formação, em todos os modos.</h2>
                            <p className="text-base leading-relaxed text-amber-100/70">
                                Monte equipes ideais com seu roster real, calcule sinergias, otimize Campanha, Torre do Rei, Reino dos Sonhos e muito
                                mais — tudo em um só lugar.
                            </p>
                            <div className="flex flex-wrap gap-2 pt-2 text-xs">
                                {[
                                    { label: 'Portadores da Luz', cls: 'border-amber-400/50 text-amber-200' },
                                    { label: 'Tumulares', cls: 'border-purple-400/50 text-purple-200' },
                                    { label: 'Selvagens', cls: 'border-emerald-400/50 text-emerald-200' },
                                    { label: 'Perversos', cls: 'border-orange-400/50 text-orange-200' },
                                    { label: 'Celestiais', cls: 'border-sky-300/60 text-sky-200' },
                                    { label: 'Hipogeus', cls: 'border-red-500/50 text-red-200' },
                                    { label: 'Dimensionais', cls: 'border-blue-400/50 text-blue-200' },
                                    { label: 'Draconis', cls: 'border-rose-400/50 text-rose-200' },
                                ].map((f) => (
                                    <span key={f.label} className={`rounded-full border bg-white/5 px-2.5 py-1 backdrop-blur-sm ${f.cls}`}>
                                        {f.label}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                </aside>

                <main className="relative flex items-center justify-center px-6 py-10 sm:px-10 lg:px-14">
                    <Link href="/" className="absolute top-6 left-6 flex items-center gap-2 lg:hidden">
                        <img src="/images/auth/afk-arena-logo.png" alt="AFK Arena" className="h-8 w-auto" />
                    </Link>

                    <div className="w-full max-w-md">
                        <div className="rounded-2xl border border-amber-400/20 bg-gradient-to-br from-zinc-900/80 via-zinc-950/90 to-black/80 p-6 shadow-[0_0_60px_-15px_rgba(251,191,36,0.35)] backdrop-blur-sm sm:p-8">
                            <div className="mb-6 space-y-2 text-center">
                                {title && (
                                    <h1 className="bg-gradient-to-r from-amber-200 via-amber-100 to-amber-300 bg-clip-text text-2xl font-bold text-transparent">
                                        {title}
                                    </h1>
                                )}
                                {description && <p className="text-sm text-amber-100/60">{description}</p>}
                            </div>
                            {children}
                        </div>

                        <p className="mt-6 text-center text-[11px] tracking-wider text-amber-100/30 uppercase">
                            AFK Builder · Não-oficial · Arte © Lilith Games
                        </p>
                    </div>
                </main>
            </div>
        </div>
    );
}
