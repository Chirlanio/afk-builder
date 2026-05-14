import { toPng } from 'html-to-image';
import { useCallback, useRef, useState } from 'react';

interface UseFormationExportOptions {
    filename?: string;
    pixelRatio?: number;
}

/**
 * Wrap o `html-to-image.toPng` num hook ergonômico:
 * - Mantém ref para o elemento alvo (preview off-screen).
 * - `download()` gera o blob, força download e setta loading.
 * - Captura erros — CORS em portraits remotos é o caso comum (img sem `crossOrigin` ou
 *   sem CORS headers do CDN). Frontend fallback: erro mostrado pro user.
 */
export function useFormationExport({ filename = 'formation.png', pixelRatio = 2 }: UseFormationExportOptions = {}) {
    const ref = useRef<HTMLDivElement | null>(null);
    const [isExporting, setIsExporting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const download = useCallback(async () => {
        if (!ref.current) {
            setError('Preview não renderizado.');
            return;
        }
        setIsExporting(true);
        setError(null);
        try {
            const dataUrl = await toPng(ref.current, {
                pixelRatio,
                cacheBust: true,
                backgroundColor: '#0a0613',
                // Skipa elementos com CORS errado em vez de quebrar
                skipFonts: false,
            });
            const link = document.createElement('a');
            link.download = filename;
            link.href = dataUrl;
            link.click();
        } catch (err) {
            console.error('Falha no export PNG:', err);
            setError(
                'Falha ao gerar imagem. Algumas imagens de heróis podem estar bloqueando exportação (CORS). Tente novamente em alguns segundos.',
            );
        } finally {
            setIsExporting(false);
        }
    }, [filename, pixelRatio]);

    return { ref, download, isExporting, error, clearError: () => setError(null) };
}
