// Los ejecutivos escriben la referencia de una cotización como quieren: "COT. 12685",
// "cot. 12624", "COT-12582-20260915", "#12500" o "JLR Maleta Visita". La interfaz ya
// antepone "Cot." / "Cot. Externa:", así que se quita el prefijo repetido y se deja "#…".
const QUOTE_PREFIX = /^(?:cotizaci[oó]n|cot)(?![a-z])\s*\.?\s*[-:]?\s*/i

export function quoteReference(value?: string | number | null): string {
    const raw = String(value ?? "").trim()
    if (!raw) return ""
    const clean = raw.replace(QUOTE_PREFIX, "").replace(/^#\s*/, "").trim()
    return `#${clean || raw}`
}
