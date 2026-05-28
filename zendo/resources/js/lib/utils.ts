import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const CURRENCY_SYMBOLS: Record<string, string> = {
    USD: '$',
    EUR: '\u20ac',
    GBP: '\u00a3',
    THB: '\u0e3f',
    JPY: '\u00a5',
    CAD: 'C$',
    AUD: 'A$',
};

export function formatCurrency(cents: number, currency: string = 'USD'): string {
    const symbol = CURRENCY_SYMBOLS[currency] ?? currency + ' ';
    const amount = (cents / 100).toFixed(2);
    return `${symbol}${amount}`;
}
