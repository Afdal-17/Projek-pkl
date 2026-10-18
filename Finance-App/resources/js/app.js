import './bootstrap';
import Alpine from 'alpinejs';

const currency = document.querySelector('meta[name="finance-currency"]')?.content ?? 'IDR';
/* Kurs live dari backend (meta finance-rates, sumber: Frankfurter + cache);
   fallback statis bila meta tidak tersedia */
const FALLBACK_RATES = { IDR: 1, USD: 16000, EUR: 17500 };
let RATES = FALLBACK_RATES;
try {
	const meta = document.querySelector('meta[name="finance-rates"]')?.content;
	const parsed = meta ? JSON.parse(meta) : null;
	if (parsed && typeof parsed === 'object' && Object.keys(parsed).length) {
		RATES = { ...FALLBACK_RATES, ...parsed };
	}
} catch (e) { /* pakai kurs fallback */ }
const rate = RATES[currency] ?? 1;
const locale = currency === 'USD' ? 'en-US' : (currency === 'EUR' ? 'de-DE' : 'id-ID');
const formatter = new Intl.NumberFormat(locale, {
	style: 'currency',
	currency,
	maximumFractionDigits: currency === 'IDR' ? 0 : 2,
});

/* Pemisah locale via Intl (id-ID: ribuan ".", desimal ","; en-US: ribuan ",",
   desimal "."; de-DE: ribuan ".", desimal ","). formatToParts(1234.5) — angka
   >= 1000 agar bagian "group" SELALU muncul; dengan 1.1 bagian itu kosong dan
   pemisah ribuan jatuh ke default yang salah untuk en-US. */
const localeParts = new Intl.NumberFormat(locale).formatToParts(1234.5);
const decimalSep = localeParts.find((p) => p.type === 'decimal')?.value ?? ',';
const groupSep = localeParts.find((p) => p.type === 'group')?.value ?? '';


function formatLargeAmount(value, currency = 'IDR') {
    let raw = String(value ?? '0').trim();

    if (!/^\d+(?:\.\d+)?$/.test(raw)) {
        return raw;
    }

    const [integerPart, decimalPart = ''] = raw.split('.');
    const groupedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    if (currency === 'IDR') {
        return `Rp ${groupedInteger}`;
    }

    const decimals = decimalPart.slice(0, 2).padEnd(2, '0');
    const separator = currency === 'EUR' ? ',' : '.';
    const grouping = currency === 'EUR' ? '.' : ',';
    const grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, grouping);

    return currency === 'USD'
        ? `$${grouped}.${decimals}`
        : `${grouped}${separator}${decimals} €`;
}


window.financeMoney = {
    currency,
    rate,
    symbol: formatter.formatToParts(0).find((part) => part.type === 'currency')?.value ?? 'Rp',

    // Saldo tersimpan dalam IDR.
    format(idrAmount) {
        if (currency === 'IDR') {
            return formatLargeAmount(idrAmount, 'IDR');
        }

        return formatter.format((Number(idrAmount) || 0) / rate);
    },

    // Nilai input dalam mata uang aktif.
    formatNative(displayAmount) {
        return formatLargeAmount(displayAmount, currency);
    },

    toBase(displayAmount) {
        return (Number(displayAmount) || 0) * rate;
    },

	fromBase(idrAmount) {
		if (currency === 'IDR') {
			return String(idrAmount ?? '0');
		}

		return String((Number(idrAmount) || 0) / rate);
	},

		parseFormatted(display) {
		let cleaned = String(display ?? '').trim().replace(/\s/g, '');

		if (groupSep) {
			cleaned = cleaned.split(groupSep).join('');
		}

		cleaned = cleaned.split(decimalSep).join('.');

		// Pertahankan angka sebagai string agar presisinya tidak hilang.
		if (!/^\d+(?:\.\d{0,2})?$/.test(cleaned)) {
			return '0';
		}

		return cleaned;
	},

	formatTyped(raw) {
		let cleaned = String(raw ?? '').trim().replace(/\s/g, '');

		if (groupSep) {
			cleaned = cleaned.split(groupSep).join('');
		}

		cleaned = cleaned.split(decimalSep).join('.');

		const trailingDecimal = cleaned.endsWith('.');
		const segments = cleaned.split('.');
		const integer = segments[0].replace(/\D/g, '') || '0';
		const decimal = (segments.slice(1).join('').replace(/\D/g, '')).slice(0, 2);

    // String, bukan Number.
		const exactValue = decimal
			? `${integer}.${decimal}`
			: integer;

		const groupedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

		const display = decimal
			? `${groupedInteger}${decimalSep}${decimal}`
			: `${groupedInteger}${trailingDecimal ? decimalSep : ''}`;

		if (/^0(?:\.0*)?$/.test(exactValue) && !trailingDecimal) {
			return { value: '0', display: '' };
    }

		return {
			value: exactValue,
			display,
		};
	},

	formatNumber(numericValue, { minDecimals = 0, maxDecimals = 2 } = {}) {
		const raw = String(numericValue ?? '0').trim();

		if (currency === 'IDR' && /^\d+(?:\.0+)?$/.test(raw)) {
			const integer = raw.split('.')[0];
			return integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		}

		if (currency === 'IDR' && /^\d+\.\d+$/.test(raw)) {
			const [integer, decimal = ''] = raw.split('.');
			const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
			return `${grouped},${decimal}`;
		}

		const num = Number(raw) || 0;

		return new Intl.NumberFormat(locale, {
			minimumFractionDigits: minDecimals,
			maximumFractionDigits: maxDecimals,
		}).format(num);
	},
};

window.Alpine = Alpine;
Alpine.start();
