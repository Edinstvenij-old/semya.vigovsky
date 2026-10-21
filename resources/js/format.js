import { intlLocale, t } from './i18n';

const locale = intlLocale();

export function date(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString(locale, { day: 'numeric', month: 'long' });
}

/** "Октябрь 2026" — month heading (CSS capitalize would also turn "г."/"р." into "Г."). */
export function monthYear(value) {
    const s = new Date(value).toLocaleDateString(locale, { month: 'long', year: 'numeric' }).replace(/\s*[гр]\.$/, '');
    return s.charAt(0).toUpperCase() + s.slice(1);
}

export function dateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleString(locale, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

export function time(value) {
    if (!value) return '';
    return new Date(value).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

/** "5 минут назад", "вчера в 14:20", "3 мая". */
export function ago(value) {
    if (!value) return '';
    const d = new Date(value);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return t('только что');
    if (diff < 3600) return t(':p0 мин назад', { p0: Math.floor(diff / 60) });
    const today = new Date();
    if (d.toDateString() === today.toDateString()) return t('сегодня в :p0', { p0: time(d) });
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return t('вчера в :p0', { p0: time(d) });
    return dateTime(d);
}

/** Days until a YYYY-MM-DD date (negative = overdue). */
export function daysUntil(ymd) {
    const [y, m, d] = ymd.slice(0, 10).split('-').map(Number);
    const target = new Date(y, m - 1, d);
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    return Math.round((target - now) / 86400000);
}

export function dueText(ymd) {
    const n = daysUntil(ymd);
    if (n < 0) return t('просрочено на :p0 дн.', { p0: -n });
    if (n === 0) return t('сегодня');
    if (n === 1) return t('завтра');
    return t('через :p0 дн.', { p0: n });
}

export function money(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toLocaleString(locale, { maximumFractionDigits: 2 }) + ' ₴';
}

/** Meter readings and other plain numbers: 24518.00 → "24 518". */
export function num(value) {
    if (value === null || value === undefined || value === '') return '';
    return Number(value).toLocaleString(locale, { maximumFractionDigits: 2 });
}

export function bytes(n) {
    if (n < 1024) return t(':p0 Б', { p0: n });
    if (n < 1048576) return t(':p0 КБ', { p0: (n / 1024).toFixed(0) });
    return t(':p0 МБ', { p0: (n / 1048576).toFixed(1) });
}

/** Value for <input type="datetime-local"> from an ISO string. */
export function toLocalInput(value) {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export const STATUS_LABELS = { todo: t('Нужно сделать'), in_progress: t('В работе'), done: t('Готово') };
export const PRIORITY_LABELS = { low: t('Не срочно'), normal: t('Обычная'), high: t('Срочно') };
export const MAINTENANCE_KINDS = { consumable: t('Расходники'), bill: t('Счёт / оплата'), check: t('Проверка'), other: t('Другое') };
export const DOC_TYPES = { flight: t('Авиабилет'), bus: t('Автобус'), train: t('Поезд'), hotel: t('Жильё'), other: t('Другое') };
export const DOC_ICONS = { flight: 'plane', bus: 'bus', train: 'train-front', hotel: 'bed', other: 'file-text' };
