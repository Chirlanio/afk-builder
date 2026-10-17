import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import Fuse from 'fuse.js';
import { Check, InfoIcon, Loader2, Search, Trash2, Upload, XCircle } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { createWorker, type Worker } from 'tesseract.js';

interface Hero {
    id: number;
    name: string;
    thumbnail_url: string;
    faction?: { id: number; name: string };
}

interface ImportProps {
    heroes: Hero[];
}

interface DetectedHero {
    id: string; // unique internal id for UI
    hero_id: number;
    name: string;
    level: number;
    confidence: number;
}

export default function RosterImport({ heroes }: ImportProps) {
    const [isProcessing, setIsProcessing] = useState(false);
    const [detectedItems, setDetectedItems] = useState<DetectedHero[]>([]);
    const [progress, setProgress] = useState(0);
    const [status, setStatus] = useState('');
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [processing, setProcessing] = useState(false);

    const fuse = useMemo(
        () =>
            new Fuse(heroes, {
                keys: ['name'],
                threshold: 0.4,
            }),
        [heroes],
    );

    // Um único worker processa as imagens em sequência — workers paralelos
    // disputariam o mesmo indicador de progresso e multiplicariam o uso de memória.
    const processImages = async (files: File[]) => {
        setIsProcessing(true);
        setProgress(0);
        setStatus('Inicializando motor de OCR...');

        let current = 1;
        const worker = await createWorker('eng', 1, {
            logger: (m) => {
                if (m.status === 'recognizing text') {
                    setProgress(Math.floor(m.progress * 100));
                    setStatus(`Reconhecendo texto (imagem ${current}/${files.length})... ${Math.floor(m.progress * 100)}%`);
                }
            },
        });

        try {
            for (const [index, file] of files.entries()) {
                current = index + 1;
                await processImage(worker, file);
            }
            setStatus('Importação concluída!');
        } catch (error) {
            console.error('OCR Error:', error);
            setStatus('Erro ao processar imagem.');
        } finally {
            await worker.terminate();
            setIsProcessing(false);
        }
    };

    const processImage = async (worker: Worker, file: File) => {
        // Desde o tesseract.js v6 a hierarquia blocks → paragraphs → lines só
        // vem quando pedida explicitamente no output.
        const { data } = await worker.recognize(file, {}, { blocks: true });

        setStatus('Processando resultados...');

        const lines = (data.blocks ?? []).flatMap((block) => block.paragraphs.flatMap((paragraph) => paragraph.lines));
        const results: DetectedHero[] = [];

        lines.forEach((line) => {
            const text = line.text.trim();
            if (text.length < 1) return;

            const levelMatch = text.match(/\b(Lvl|Level|L|v)?\s*(\d{1,3})\b/i);
            const level = levelMatch ? parseInt(levelMatch[2]) : null;

            const nameCandidate = text.replace(/[^a-zA-Z\s]/g, '').trim();

            if (nameCandidate.length >= 3) {
                const fuseResults = fuse.search(nameCandidate);
                if (fuseResults.length > 0) {
                    const bestMatch = fuseResults[0];

                    results.push({
                        id: Math.random().toString(36).substr(2, 9),
                        hero_id: bestMatch.item.id,
                        name: bestMatch.item.name,
                        level: level || 1,
                        confidence: 1 - (bestMatch.score || 0),
                    });
                }
            } else if (level && level > 1) {
                if (levelMatch && levelMatch[0].length >= 2) {
                    results.push({
                        id: Math.random().toString(36).substr(2, 9),
                        hero_id: 0,
                        name: 'Herói não identificado',
                        level: level,
                        confidence: 0.5,
                    });
                }
            }
        });

        const uniqueResults = results.reduce((acc, current) => {
            if (current.hero_id === 0) return acc.concat([current]);

            const x = acc.find((item) => item.hero_id === current.hero_id);
            if (!x) {
                return acc.concat([current]);
            } else {
                if (current.level > x.level) {
                    return acc.map((i) => (i.hero_id === current.hero_id ? current : i));
                }
                return acc;
            }
        }, [] as DetectedHero[]);

        setDetectedItems((prev) => [...prev, ...uniqueResults]);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0 && !isProcessing) {
            processImages(Array.from(e.target.files));
        }
        // Permite reenviar o mesmo arquivo depois de limpar a lista.
        e.target.value = '';
    };

    const removeItem = (id: string) => {
        setDetectedItems((prev) => prev.filter((item) => item.id !== id));
    };

    const clearAll = () => {
        setDetectedItems([]);
    };

    const handleSave = () => {
        // O mesmo herói pode ter sido identificado em mais de uma imagem — fica o maior nível.
        const levels = new Map<number, number>();
        detectedItems
            .filter((item) => item.hero_id !== 0)
            .forEach((item) => {
                const level = Number.isFinite(item.level) && item.level > 0 ? item.level : 1;
                levels.set(item.hero_id, Math.max(levels.get(item.hero_id) ?? 0, level));
            });

        const payload = Array.from(levels, ([hero_id, level]) => ({ hero_id, level }));
        if (payload.length === 0) return;

        router.post(
            route('roster.import.store'),
            { heroes: payload },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setDetectedItems([]),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Importar Roster via OCR" />
            <PageShell
                title="Importar Roster"
                description="Use inteligência artificial para ler capturas de tela e atualizar seu roster automaticamente."
            >
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="h-fit lg:col-span-1">
                        <CardHeader>
                            <CardTitle>Upload de Screenshots</CardTitle>
                            <CardDescription>
                                Envie imagens das telas de Retratos (Portraits), Cristal de Ressonância ou sua lista de Heróis.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div
                                className="border-muted hover:border-primary cursor-pointer rounded-lg border-2 border-dashed p-12 text-center transition-colors"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <Upload className="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                                <p className="text-sm font-medium">Clique para selecionar</p>
                                <p className="text-muted-foreground mt-2 text-xs">Suporta múltiplas imagens</p>
                            </div>
                            <input type="file" ref={fileInputRef} className="hidden" multiple accept="image/*" onChange={handleFileChange} />

                            {isProcessing && (
                                <div className="bg-accent/50 space-y-2 rounded-lg p-4">
                                    <div className="flex items-center justify-between text-xs font-medium">
                                        <span>{status}</span>
                                        <span>{progress}%</span>
                                    </div>
                                    <div className="bg-muted h-2 w-full rounded-full">
                                        <div className="bg-primary h-2 rounded-full transition-all duration-300" style={{ width: `${progress}%` }} />
                                    </div>
                                </div>
                            )}

                            <div className="space-y-3">
                                <Alert className="bg-primary/5 border-primary/20">
                                    <InfoIcon className="text-primary h-4 w-4" />
                                    <AlertTitle className="text-primary">Dica de Precisão</AlertTitle>
                                    <AlertDescription className="text-xs">
                                        Screenshots da aba <strong>"Retratos"</strong> são ideais. Se usar a aba <strong>"Heróis"</strong>, o nível
                                        será lido, mas você precisará identificar o herói manualmente.
                                    </AlertDescription>
                                </Alert>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Heróis Detectados ({detectedItems.length})</CardTitle>
                                <CardDescription>Os heróis em vermelho precisam ser identificados manualmente.</CardDescription>
                            </div>
                            <div className="flex items-center gap-2">
                                {detectedItems.length > 0 && (
                                    <>
                                        <Button variant="outline" size="sm" onClick={clearAll} disabled={processing}>
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Limpar
                                        </Button>
                                        <Button
                                            size="sm"
                                            onClick={handleSave}
                                            disabled={processing || detectedItems.filter((i) => i.hero_id !== 0).length === 0}
                                        >
                                            {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Check className="mr-2 h-4 w-4" />}
                                            Importar {detectedItems.filter((i) => i.hero_id !== 0).length} heróis
                                        </Button>
                                    </>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {detectedItems.length === 0 ? (
                                <div className="text-muted-foreground flex flex-col items-center justify-center rounded-xl border-2 border-dashed py-20">
                                    <Search className="mb-4 h-10 w-10 opacity-20" />
                                    <p>Nenhum herói detectado ainda.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    {detectedItems.map((item) => {
                                        const hero = heroes.find((h) => h.id === item.hero_id);
                                        const isUnidentified = item.hero_id === 0;

                                        return (
                                            <div
                                                key={item.id}
                                                className={`bg-card group flex items-center rounded-lg border p-3 transition-all ${isUnidentified ? 'border-destructive/30 bg-destructive/5' : 'hover:bg-accent/50'}`}
                                            >
                                                <div className="relative mr-3 h-12 w-12 shrink-0">
                                                    {isUnidentified ? (
                                                        <div className="bg-muted border-muted-foreground/30 flex h-full w-full items-center justify-center rounded-md border border-dashed">
                                                            <Search className="text-muted-foreground h-5 w-5" />
                                                        </div>
                                                    ) : (
                                                        <img
                                                            src={hero?.thumbnail_url}
                                                            alt={item.name}
                                                            className="border-muted h-full w-full rounded-md border object-cover shadow-sm"
                                                        />
                                                    )}
                                                </div>

                                                <div className="min-w-0 flex-1">
                                                    <div className="mb-1 flex items-center justify-between">
                                                        {isUnidentified ? (
                                                            <Popover>
                                                                <PopoverTrigger asChild>
                                                                    <Button
                                                                        variant="link"
                                                                        className="text-destructive h-auto truncate p-0 text-sm font-semibold underline decoration-dotted"
                                                                    >
                                                                        Identificar Herói
                                                                    </Button>
                                                                </PopoverTrigger>
                                                                <PopoverContent className="w-[200px] p-0" align="start">
                                                                    <Command>
                                                                        <CommandInput placeholder="Buscar herói..." />
                                                                        <CommandList>
                                                                            <CommandEmpty>Não encontrado.</CommandEmpty>
                                                                            <CommandGroup>
                                                                                {heroes.map((h) => (
                                                                                    <CommandItem
                                                                                        key={h.id}
                                                                                        value={h.name}
                                                                                        onSelect={() => {
                                                                                            setDetectedItems((prev) =>
                                                                                                prev.map((i) =>
                                                                                                    i.id === item.id
                                                                                                        ? {
                                                                                                              ...i,
                                                                                                              hero_id: h.id,
                                                                                                              name: h.name,
                                                                                                              confidence: 1,
                                                                                                          }
                                                                                                        : i,
                                                                                                ),
                                                                                            );
                                                                                        }}
                                                                                    >
                                                                                        <img src={h.thumbnail_url} className="mr-2 h-6 w-6 rounded" />
                                                                                        {h.name}
                                                                                    </CommandItem>
                                                                                ))}
                                                                            </CommandGroup>
                                                                        </CommandList>
                                                                    </Command>
                                                                </PopoverContent>
                                                            </Popover>
                                                        ) : (
                                                            <p className="truncate text-sm font-semibold">{item.name}</p>
                                                        )}
                                                        <Badge
                                                            variant={item.confidence > 0.8 ? 'default' : 'secondary'}
                                                            className="h-3 px-1 text-[9px]"
                                                        >
                                                            {Math.round(item.confidence * 100)}%
                                                        </Badge>
                                                    </div>

                                                    <div className="flex items-center gap-2">
                                                        <span className="text-muted-foreground text-[10px] font-bold uppercase">Nível</span>
                                                        <Input
                                                            type="number"
                                                            value={item.level}
                                                            onChange={(e) => {
                                                                const val = parseInt(e.target.value);
                                                                setDetectedItems((prev) =>
                                                                    prev.map((i) => (i.id === item.id ? { ...i, level: val } : i)),
                                                                );
                                                            }}
                                                            className="h-7 w-20 font-mono text-xs"
                                                        />
                                                    </div>
                                                </div>

                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="ml-1 h-8 w-8 opacity-0 transition-opacity group-hover:opacity-100"
                                                    onClick={() => removeItem(item.id)}
                                                >
                                                    <XCircle className="text-muted-foreground hover:text-destructive h-4 w-4" />
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </PageShell>
        </AppLayout>
    );
}
