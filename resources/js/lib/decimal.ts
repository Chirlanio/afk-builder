// Números digitados pelo jogador: aceita o padrão brasileiro (vírgula decimal,
// ponto de milhar) sem quebrar quem digita ponto como decimal.
//
//   "12,5"       → 12.5      vírgula = decimal
//   "1.234,56"   → 1234.56   ponto(s) antes da vírgula = milhar
//   "1.234.567"  → 1234567   pontos em grupos de 3 = milhar
//   "1.234"      → 1234      (padrão BR — como o jogo mostra milhar)
//   "12.5"       → 12.5      ponto sem grupo de 3 = decimal
//   "1234.56"    → 1234.56
//   ""           → null      campo vazio
//   "abc", "1,2,3" → NaN     inválido (quem chama mostra o erro)

export function parseDecimal(raw: string | number | null | undefined): number | null {
    if (raw === null || raw === undefined) return null;
    if (typeof raw === 'number') return Number.isFinite(raw) ? raw : NaN;

    let s = raw.trim().replace(/\s/g, '');
    if (s === '') return null;

    if (s.includes(',')) {
        if ((s.match(/,/g) ?? []).length > 1) return NaN;
        s = s.replace(/\./g, '').replace(',', '.');
    } else if (/^\d{1,3}(\.\d{3})+$/.test(s)) {
        s = s.replace(/\./g, '');
    }

    if (!/^-?\d+(\.\d+)?$/.test(s)) return NaN;

    return Number(s);
}

/** Valor salvo → texto do campo, com vírgula decimal (padrão brasileiro). */
export function formatDecimalInput(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '';
    const n = typeof value === 'number' ? value : Number(value);
    if (!Number.isFinite(n)) return String(value);
    // Sem separador de milhar no campo (facilita editar); até 2 casas, sem zeros à direita.
    return String(Math.round(n * 100) / 100).replace('.', ',');
}
