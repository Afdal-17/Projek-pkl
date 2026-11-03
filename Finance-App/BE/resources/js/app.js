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
	/* Desimal mengikuti ketikan user (0.1 tampil 0.1, bukan dipaksa 0.10).
	   IDR tetap tanpa desimal; USD/EUR maksimal 2. */
	minimumFractionDigits: 0,
	maximumFractionDigits: currency === 'IDR' ? 0 : 2,
});

/* Pemisah locale via Intl (id-ID: ribuan ".", desimal ","; en-US: ribuan ",",
   desimal "."; de-DE: ribuan ".", desimal ","). formatToParts(1234.5) — angka
   >= 1000 agar bagian "group" SELALU muncul; dengan 1.1 bagian itu kosong dan
   pemisah ribuan jatuh ke default yang salah untuk en-US. */
const localeParts = new Intl.NumberFormat(locale).formatToParts(1234.5);
const decimalSep = localeParts.find((p) => p.type === 'decimal')?.value ?? ',';
const groupSep = localeParts.find((p) => p.type === 'group')?.value ?? '';

window.financeMoney = {
	currency,
	rate,
	symbol: formatter.formatToParts(0).find((part) => part.type === 'currency')?.value ?? 'Rp',
	/* Nilai tersimpan dalam IDR (base); konversi ke mata uang aktif saat menampilkan */
	format(idrAmount) {
		return formatter.format((Number(idrAmount) || 0) / rate);
	},
	/* Format nilai yang sudah dalam mata uang aktif (input user) */
	formatNative(displayAmount) {
		return formatter.format(Number(displayAmount) || 0);
	},
	/* Konversi nilai input (mata uang aktif) ke IDR base */
	toBase(displayAmount) {
		return (Number(displayAmount) || 0) * rate;
	},
	/* Konversi nilai IDR base ke mata uang aktif */
	fromBase(idrAmount) {
		return (Number(idrAmount) || 0) / rate;
	},
	/* Format nilai NUMERIK menjadi teks tampilan berpemisah ribuan (tanpa
	   simbol mata uang). Berbeda dari formatTyped/formatNative: fungsi ini
	   menerima angka (mis. 1234567.89) dan menghasilkan teks sesuai locale
	   ("1.234.567,89" di id-ID). Aman dipakai untuk nilai dari backend. */
	formatNumber(numericValue, { minDecimals = 0, maxDecimals = 2 } = {}) {
		const num = Number(numericValue) || 0;
		return new Intl.NumberFormat(locale, {
			minimumFractionDigits: minDecimals,
			maximumFractionDigits: maxDecimals,
		}).format(num);
	},
	/* Parse string tampilan (berpemisah ribuan sesuai locale) menjadi angka.
	   Pemisah ribuan dibuang, pemisah desimal dijadikan "." — aman untuk
	   id-ID ("1.234,5" -> 1234.5) maupun en-US ("1,234.5" -> 1234.5). */
	parseFormatted(display) {
		let cleaned = String(display ?? '').trim().replace(/\s/g, '');
		if (groupSep) cleaned = cleaned.split(groupSep).join('');
		cleaned = cleaned.split(decimalSep).join('.');
		const value = parseFloat(cleaned);
		return Number.isFinite(value) ? value : 0;
	},
	/* Format nilai yang sedang diketik menjadi string tampilan berpemisah
	   ribuan, sambil mempertahankan pemisah desimal yang belum diisi angka
	   ("12," / "12.") agar user bisa lanjut mengetik desimal. Mengembalikan
	   { value, display } — value angka hasil parse, display string tampilan. */
	formatTyped(raw) {
		let cleaned = String(raw ?? '').replace(/\s/g, '');
		if (groupSep) cleaned = cleaned.split(groupSep).join('');
		cleaned = cleaned.split(decimalSep).join('.');

		const trailingDecimal = cleaned.endsWith('.') ? '.' : '';

		const segments = cleaned.split('.');
		const integerStr = segments[0].replace(/\D/g, '') || '0';
		const integerClean = integerStr.length > 1 ? integerStr.replace(/^0+/, '') || '0' : integerStr;
		const decimal = segments.slice(1).join('').replace(/\D/g, '').slice(0, 2);

		let formattedInteger = integerClean;
		if (groupSep) {
			formattedInteger = integerClean.replace(/\B(?=(\d{3})+(?!\d))/g, groupSep);
		}

		let display = formattedInteger;
		if (decimal) {
			display += decimalSep + decimal;
		} else if (trailingDecimal) {
			display += decimalSep;
		}

		const num = parseFloat(decimal ? `${integerClean}.${decimal}` : integerClean) || 0;

		if (num === 0 && !trailingDecimal && integerClean === '0') {
			return { value: 0, display: '' };
		}

		return {
			value: num,
			display: display,
		};
	},
};

window.Alpine = Alpine;
Alpine.start();
