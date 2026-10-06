import './bootstrap';
import Alpine from 'alpinejs';

const currency = document.querySelector('meta[name="finance-currency"]')?.content ?? 'IDR';
const locale = currency === 'USD' ? 'en-US' : (currency === 'EUR' ? 'de-DE' : 'id-ID');
const formatter = new Intl.NumberFormat(locale, {
	style: 'currency',
	currency,
	maximumFractionDigits: currency === 'IDR' ? 0 : 2,
});

window.financeMoney = {
	symbol: formatter.formatToParts(0).find((part) => part.type === 'currency')?.value ?? 'Rp',
	format(amount) {
		return formatter.format(Number(amount) || 0);
	},
};

window.Alpine = Alpine;
Alpine.start();