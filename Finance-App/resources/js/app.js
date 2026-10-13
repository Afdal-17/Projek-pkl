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
};

window.Alpine = Alpine;
Alpine.start();
