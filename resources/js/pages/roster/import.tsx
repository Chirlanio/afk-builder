import AppLayout from '@/layouts/app-layout';
import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState, useRef, useMemo } from 'react';
import { createWorker } from 'tesseract.js';
import Fuse from 'fuse.js';
import { Head, useForm } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { InfoIcon, Loader2, Upload, Check, Trash2, Search, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';

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

    const { post, processing } = useForm({
        heroes: [] as { hero_id: number; level: number }[],
    });

    const fuse = useMemo(() => new Fuse(heroes, {
        keys: ['name'],
        threshold: 0.4,
    }), [heroes]);

    const processImage = async (file: File) => {
        setIsProcessing(true);
        setProgress(0);
        setStatus('Inicializando motor de OCR...');

        const worker = await createWorker('eng', 1, {
            logger: (m) => {
                if (m.status === 'recognizing text') {
                    setProgress(Math.floor(m.progress * 100));
                    setStatus(`Reconhecendo texto... ${Math.floor(m.progress * 100)}%`);
                }
            },
        });

        try {
            const url = URL.createObjectURL(file);
            const { data: { lines } } = await worker.recognize(url);
            
            setStatus('Processando resultados...');
            
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
                
                const x = acc.find(item => item.hero_id === current.hero_id);
                if (!x) {
                    return acc.concat([current]);
                } else {
                    if (current.level > x.level) {
                        return acc.map(i => i.hero_id === current.hero_id ? current : i);
                    }
                    return acc;
                }
            }, [] as DetectedHero[]);

            setDetectedItems(prev => [...prev, ...uniqueResults]);
            setStatus('Importação concluída!');
        } catch (error) {
            console.error('OCR Error:', error);
            setStatus('Erro ao processar imagem.');
        } finally {
            await worker.terminate();
            setIsProcessing(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            Array.from(e.target.files).forEach(file => processImage(file));
        }
    };

    const removeItem = (id: string) => {
        setDetectedItems(prev => prev.filter(item => item.id !== id));
    };

    const clearAll = () => {
        setDetectedItems([]);
    };

    const handleSave = () => {
        const payload = detectedItems
            .filter(item => item.hero_id !== 0)
            .map(item => ({
                hero_id: item.hero_id,
                level: item.level
            }));
        
        if (payload.length === 0) return;

        post(route('roster.import.store'), {
            data: { heroes: payload },
            onSuccess: () => {
                setDetectedItems([]);
            }
        });
    };

    return (
        <AppLayout>
            <Head title="Importar Roster via OCR" />
            <PageShell
                title="Importar Roster"
                description="Use inteligência artificial para ler capturas de tela e atualizar seu roster automaticamente."
            >
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <Card className="lg:col-span-1 h-fit">
                        <CardHeader>
                            <CardTitle>Upload de Screenshots</CardTitle>
                            <CardDescription>
                                Envie imagens das telas de Retratos (Portraits), Cristal de Ressonância ou sua lista de Heróis.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div 
                                className="border-2 border-dashed border-muted rounded-lg p-12 text-center hover:border-primary cursor-pointer transition-colors"
                                onClick={() => fileInputRef.current?.click()}
                            >
                                <Upload className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-sm font-medium">Clique para selecionar</p>
                                <p className="text-xs text-muted-foreground mt-2">Suporta múltiplas imagens</p>
                            </div>
                            <input 
                                type="file" 
                                ref={fileInputRef} 
                                className="hidden" 
                                multiple 
                                accept="image/*" 
                                onChange={handleFileChange}
                            />

                            {isProcessing && (
                                <div className="space-y-2 p-4 bg-accent/50 rounded-lg">
                                    <div className="flex items-center justify-between text-xs font-medium">
                                        <span>{status}</span>
                                        <span>{progress}%</span>
                                    </div>
                                    <div className="w-full bg-muted rounded-full h-2">
                                        <div 
                                            className="bg-primary h-2 rounded-full transition-all duration-300" 
                                            style={{ width: `${progress}%` }}
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="space-y-3">
                                <Alert className="bg-primary/5 border-primary/20">
                                    <InfoIcon className="h-4 w-4 text-primary" />
                                    <AlertTitle className="text-primary">Dica de Precisão</AlertTitle>
                                    <AlertDescription className="text-xs">
                                        Screenshots da aba <strong>"Retratos"</strong> são ideais. Se usar a aba <strong>"Heróis"</strong>, o nível será lido, mas você precisará identificar o herói manualmente.
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
                                        <Button size="sm" onClick={handleSave} disabled={processing || detectedItems.filter(i => i.hero_id !== 0).length === 0}>
                                            {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Check className="mr-2 h-4 w-4" />}
                                            Importar {detectedItems.filter(i => i.hero_id !== 0).length} heróis
                                        </Button>
                                    </>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent>
                            {detectedItems.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-20 text-muted-foreground border-2 border-dashed rounded-xl">
                                    <Search className="h-10 w-10 mb-4 opacity-20" />
                                    <p>Nenhum herói detectado ainda.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {detectedItems.map((item) => {
                                        const hero = heroes.find(h => h.id === item.hero_id);
                                        const isUnidentified = item.hero_id === 0;

                                        return (
                                            <div key={item.id} className={`flex items-center p-3 border rounded-lg bg-card transition-all group ${isUnidentified ? 'border-destructive/30 bg-destructive/5' : 'hover:bg-accent/50'}`}>
                                                <div className="relative w-12 h-12 mr-3 shrink-0">
                                                    {isUnidentified ? (
                                                        <div className="w-full h-full rounded-md bg-muted flex items-center justify-center border border-dashed border-muted-foreground/30">
                                                            <Search className="h-5 w-5 text-muted-foreground" />
                                                        </div>
                                                    ) : (
                                                        <img 
                                                            src={hero?.thumbnail_url} 
                                                            alt={item.name} 
                                                            className="w-full h-full rounded-md object-cover border border-muted shadow-sm"
                                                        />
                                                    )}
                                                </div>
                                                
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center justify-between mb-1">
                                                        {isUnidentified ? (
                                                            <Popover>
                                                                <PopoverTrigger asChild>
                                                                    <Button variant="link" className="p-0 h-auto text-sm font-semibold text-destructive underline decoration-dotted truncate">
                                                                        Identificar Herói
                                                                    </Button>
                                                                </PopoverTrigger>
                                                                <PopoverContent className="p-0 w-[200px]" align="start">
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
                                                                                            setDetectedItems(prev => prev.map(i => i.id === item.id ? { ...i, hero_id: h.id, name: h.name, confidence: 1 } : i));
                                                                                        }}
                                                                                    >
                                                                                        <img src={h.thumbnail_url} className="w-6 h-6 rounded mr-2" />
                                                                                        {h.name}
                                                                                    </CommandItem>
                                                                                ))}
                                                                            </CommandGroup>
                                                                        </CommandList>
                                                                    </Command>
                                                                </PopoverContent>
                                                            </Popover>
                                                        ) : (
                                                            <p className="text-sm font-semibold truncate">{item.name}</p>
                                                        )}
                                                        <Badge variant={item.confidence > 0.8 ? "default" : "secondary"} className="text-[9px] h-3 px-1">
                                                            {Math.round(item.confidence * 100)}%
                                                        </Badge>
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-[10px] uppercase font-bold text-muted-foreground">Nível</span>
                                                        <Input 
                                                            type="number" 
                                                            value={item.level} 
                                                            onChange={(e) => {
                                                                const val = parseInt(e.target.value);
                                                                setDetectedItems(prev => prev.map(i => i.id === item.id ? { ...i, level: val } : i));
                                                            }}
                                                            className="h-7 w-20 text-xs font-mono"
                                                        />
                                                    </div>
                                                </div>

                                                <Button 
                                                    variant="ghost" 
                                                    size="icon" 
                                                    className="h-8 w-8 opacity-0 group-hover:opacity-100 transition-opacity ml-1"
                                                    onClick={() => removeItem(item.id)}
                                                >
                                                    <XCircle className="h-4 w-4 text-muted-foreground hover:text-destructive" />
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
