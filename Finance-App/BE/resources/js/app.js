
import './bootstrap';
import Alpine from 'alpinejs';

const currency =
    document.querySelector('meta[name="finance-currency"]')?.content ?? 'IDR';

const FALLBACK_RATES = {
    IDR: 1,
    USD: 16000,
    EUR: 17500,
};

let RATES = { ...FALLBACK_RATES };

try {
    const meta = document.querySelector(
        'meta[name="finance-rates"]'
    )?.content;

    const parsed = meta ? JSON.parse(meta) : null;

    if (parsed && typeof parsed === 'object') {
        RATES = { ...FALLBACK_RATES, ...parsed };
    }
} catch {
    // Gunakan kurs fallback jika metadata tidak valid.
}

const rate = RATES[currency] ?? 1;

const locale =
    currency === 'USD'
        ? 'en-US'
        : currency === 'EUR'
            ? 'de-DE'
            : 'id-ID';

const formatter = new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'IDR' ? 0 : 2,
});

const localeParts = new Intl.NumberFormat(locale).formatToParts(1234.5);

const decimalSep =
    localeParts.find((part) => part.type === 'decimal')?.value ?? ',';

const groupSep =
    localeParts.find((part) => part.type === 'group')?.value ?? '.';

function formatLargeAmount(value, currencyCode = 'IDR') {
    const raw = String(value ?? '0').trim();
    const match = raw.match(/^(-?)(\d+)(?:\.(\d+))?$/);

    if (!match) {
        return raw;
    }

    const sign = match[1];
    const integerPart = match[2];
    const decimalPart = match[3] ?? '';

    const groupedInteger = integerPart.replace(
        /\B(?=(\d{3})+(?!\d))/g,
        groupSep
    );

    if (currencyCode === 'IDR') {
        return `Rp ${sign}${groupedInteger}`;
    }

    const decimals = decimalPart.slice(0, 2).padEnd(2, '0');

    if (currencyCode === 'USD') {
        return `$${sign}${groupedInteger}.${decimals}`;
    }

    return `${sign}${groupedInteger}${decimalSep}${decimals} €`;
}

// Ubah angka desimal menjadi satuan sen tanpa Number().
function decimalToCents(value) {
    let raw = String(value ?? '0').trim();

    if (!/^-?\d+(?:\.\d*)?$/.test(raw)) {
        return 0n;
    }

    const negative = raw.startsWith('-');

    if (negative) {
        raw = raw.slice(1);
    }

    const [whole, fraction = ''] = raw.split('.');

    let cents =
        BigInt(whole) * 100n +
        BigInt((fraction + '00').slice(0, 2));

    if (fraction.length > 2 && fraction[2] >= '5') {
        cents += 1n;
    }

    return negative ? -cents : cents;
}

function centsToDecimal(cents) {
    const negative = cents < 0n;

    if (negative) {
        cents = -cents;
    }

    return `${negative ? '-' : ''}${cents / 100n}.${String(
        cents % 100n
    ).padStart(2, '0')}`;
}

function roundedDivide(numerator, denominator) {
    if (denominator === 0n) {
        return 0n;
    }

    const negative =
        (numerator < 0n) !== (denominator < 0n);

    if (numerator < 0n) numerator = -numerator;
    if (denominator < 0n) denominator = -denominator;

    const result =
        (numerator + denominator / 2n) / denominator;

    return negative ? -result : result;
}

// Representasikan kurs desimal sebagai pecahan bilangan bulat.
function getRateFraction(value) {
    const raw = String(value ?? '1');

    if (!/^\d+(?:\.\d+)?$/.test(raw)) {
        return { numerator: 1n, scale: 1n };
    }

    const [whole, fraction = ''] = raw.split('.');

    return {
        numerator: BigInt(whole + fraction),
        scale: 10n ** BigInt(fraction.length),
    };
}

function convertBaseToDisplay(amount) {
    const { numerator, scale } = getRateFraction(rate);

    return centsToDecimal(
        roundedDivide(
            decimalToCents(amount) * scale,
            numerator
        )
    );
}

function convertDisplayToBase(amount) {
    const { numerator, scale } = getRateFraction(rate);

    return centsToDecimal(
        roundedDivide(
            decimalToCents(amount) * numerator,
            scale
        )
    );
}

function formatTyped(raw) {
    let cleaned = String(raw ?? '').trim().replace(/\s/g, '');

    if (groupSep) {
        cleaned = cleaned.split(groupSep).join('');
    }

    cleaned = cleaned.split(decimalSep).join('.');

    const trailingDecimal = cleaned.endsWith('.');
    const segments = cleaned.split('.');
    const integer = segments[0].replace(/\D/g, '') || '0';
    const decimal = (segments.slice(1).join('').replace(/\D/g, '')).slice(0, 2);

    const exactValue = decimal
        ? `${integer}.${decimal}`
        : integer;

    const groupedInteger = integer.replace(
        /\B(?=(\d{3})+(?!\d))/g,
        groupSep
    );

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
}

window.financeMoney = {
    currency,
    rate,

    symbol:
        formatter.formatToParts(0).find(
            (part) => part.type === 'currency'
        )?.value ?? 'Rp',

    // Saldo dari database selalu disimpan dalam IDR.
    format(idrAmount) {
        if (currency === 'IDR') {
            return formatLargeAmount(idrAmount, 'IDR');
        }

        return formatLargeAmount(
            convertBaseToDisplay(idrAmount),
            currency
        );
    },

    // Konversi nilai input dari mata uang aktif ke IDR.
    toBase(displayAmount) {
        return convertDisplayToBase(displayAmount);
    },

    // Konversi saldo IDR ke mata uang aktif.
    fromBase(idrAmount) {
        if (currency === 'IDR') {
            return String(idrAmount ?? '0');
        }

        return convertBaseToDisplay(idrAmount);
    },

    formatNative(displayAmount) {
        return formatLargeAmount(displayAmount, currency);
    },

    parseFormatted(display) {
        let cleaned = String(display ?? '').trim().replace(/\s/g, '');

        if (groupSep) {
            cleaned = cleaned.split(groupSep).join('');
        }

        cleaned = cleaned.split(decimalSep).join('.');

        if (!/^\d+(?:\.\d{0,2})?$/.test(cleaned)) {
            return '0';
        }

        return cleaned;
    },

    compare(left, right) {
        const a = decimalToCents(left);
        const b = decimalToCents(right);

        return a > b ? 1 : a < b ? -1 : 0;
    },

    add(...values) {
        return centsToDecimal(
            values.reduce((sum, value) => sum + decimalToCents(value), 0n)
        );
    },

    subtract(left, right) {
        return centsToDecimal(decimalToCents(left) - decimalToCents(right));
    },

    min(left, right) {
        return this.compare(left, right) <= 0 ? String(left) : String(right);
    },

    percent(amount, total) {
        const totalCents = decimalToCents(total);

        if (totalCents <= 0n) {
            return 0;
        }

        return Number(
            roundedDivide(decimalToCents(amount) * 100n, totalCents)
        );
    },

    formatTyped,

    formatNumber(
        numericValue,
        { minDecimals = 0, maxDecimals = 2 } = {}
    ) {
        const raw = String(numericValue ?? '0').trim();

        const match = raw.match(/^(-?)(\d+)(?:\.(\d+))?$/);

        if (!match) {
            return raw;
        }

        const sign = match[1];
        const integer = match[2].replace(/\B(?=(\d{3})+(?!\d))/g, groupSep);
        let fraction = (match[3] ?? '').slice(0, maxDecimals);

        while (fraction.length > minDecimals && fraction.endsWith('0')) {
            fraction = fraction.slice(0, -1);
        }

        fraction = fraction.padEnd(minDecimals, '0');

        return `${sign}${integer}${fraction ? decimalSep + fraction : ''}`;
    },
};

function formatMoneyInput(input) {
    const initialValue = input.value.trim();
    const canonicalValue = initialValue === ''
        ? ''
        : /^\d+(?:\.\d{1,2})?$/.test(initialValue)
            ? initialValue
            : window.financeMoney.parseFormatted(initialValue);

    input.dataset.moneyValue = canonicalValue;
    input.value = canonicalValue === '' || canonicalValue === '0'
        ? ''
        : window.financeMoney.formatNumber(canonicalValue);
}

document.querySelectorAll('input[data-money-input]').forEach(formatMoneyInput);

document.addEventListener('input', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || !input.matches('input[data-money-input]')) {
        return;
    }

    const cursor = input.selectionStart ?? input.value.length;
    const digitsBeforeCursor = input.value.slice(0, cursor).replace(/\D/g, '').length;
    const result = window.financeMoney.formatTyped(input.value);
    input.dataset.moneyValue = result.value;
    input.value = result.display;

    let nextCursor = 0;
    let digitsSeen = 0;

    while (nextCursor < input.value.length && digitsSeen < digitsBeforeCursor) {
        if (/\d/.test(input.value[nextCursor])) {
            digitsSeen += 1;
        }
        nextCursor += 1;
    }

    input.setSelectionRange(nextCursor, nextCursor);

    if (input.id === 'saldo' || input.id === 'saldo_awal') {
        const otherInput = document.getElementById(input.id === 'saldo' ? 'saldo_awal' : 'saldo');

        if (input.value && otherInput instanceof HTMLInputElement) {
            otherInput.value = '';
            otherInput.dataset.moneyValue = '';
        }
    }
});

document.addEventListener('submit', (event) => {
    if (!(event.target instanceof HTMLFormElement)) {
        return;
    }

    event.target.querySelectorAll('input[data-money-input][name]').forEach((input) => {
        input.value = input.dataset.moneyValue ?? window.financeMoney.parseFormatted(input.value);
    });
}, true);

window.Alpine = Alpine;
Alpine.start();
