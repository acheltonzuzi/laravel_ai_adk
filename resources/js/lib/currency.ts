function currencyFormatter(fractionDigits: number): Intl.NumberFormat {
    return new Intl.NumberFormat('pt-AO', {
        style: 'currency',
        currency: 'AOA',
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
}

const wholeFormatter = currencyFormatter(0);
const centsFormatter = currencyFormatter(2);

/**
 * Round values are shown whole, but cents are never rounded away: a price of
 * 18.750.000,50 must read as such, not as 18.750.001 nor as ",5".
 */
export function formatCurrency(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return Number.isInteger(value)
        ? wholeFormatter.format(value)
        : centsFormatter.format(value);
}
