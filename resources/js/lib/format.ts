// The only place that formats money, percent, and dates for display (PRD Arsitektur 9.2, A14).
// Data arrives as integers (rupiah) and ISO 8601 UTC strings; the screen shows id-ID + WITA.

export const DISPLAY_TIMEZONE = 'Asia/Makassar';

const rupiahFull = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const decimalOne = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });

/** PRD Fitur 8.1: "Rp85.000". No space after "Rp", dot as thousands separator. */
export function formatRupiah(amount: number): string {
    const sign = amount < 0 ? '-' : '';
    return `${sign}Rp${rupiahFull.format(Math.abs(Math.round(amount)))}`;
}

/** PRD Fitur 8.1: "Rp4,2 juta" for summaries. Below one million falls back to the full form. */
export function formatRupiahShort(amount: number): string {
    const abs = Math.abs(amount);
    const sign = amount < 0 ? '-' : '';
    if (abs >= 1_000_000_000) {
        return `${sign}Rp${decimalOne.format(abs / 1_000_000_000)} miliar`;
    }
    if (abs >= 1_000_000) {
        return `${sign}Rp${decimalOne.format(abs / 1_000_000)} juta`;
    }
    return formatRupiah(amount);
}

/**
 * PRD Fitur 8.1: percent without decimals. The value is already rounded up by the backend
 * (PriceLabeler); this only prints it, so "+0%" can never be produced here.
 */
export function formatPercentAbove(percent: number): string {
    return `+${Math.trunc(percent)}%`;
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function witaParts(iso: string): Record<string, string> {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        throw new RangeError(`Invalid ISO date: ${iso}`);
    }
    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: DISPLAY_TIMEZONE,
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);
    return Object.fromEntries(parts.map((p) => [p.type, p.value]));
}

/** PRD Fitur 8.1: "14 Des 2026". */
export function formatDate(iso: string): string {
    const p = witaParts(iso);
    return `${Number(p.day)} ${MONTHS[Number(p.month) - 1]} ${p.year}`;
}

/** PRD Fitur 8.1: "14 Des 2026, 09.15 WITA". */
export function formatDateTime(iso: string): string {
    const p = witaParts(iso);
    return `${formatDate(iso)}, ${p.hour}.${p.minute} WITA`;
}

/** Panjang dalam meter untuk warga: "350 m", "2,5 m"; di bawah 1 meter memakai cm ("15 cm"). */
export function formatLength(meters: number): string {
    if (meters > 0 && meters < 1) {
        return `${Math.round(meters * 100)} cm`;
    }
    return `${decimalOne.format(meters)} m`;
}
