const locale = 'ru-RU';

export function date(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString(locale, { day: 'numeric', month: 'long' });
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
    if (diff < 60) return 'только что';
    if (diff < 3600) return `${Math.floor(diff / 60)} мин назад`;
    const today = new Date();
    if (d.toDateString() === today.toDateString()) return `сегодня в ${time(d)}`;
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return `вчера в ${time(d)}`;
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
    if (n < 0) return `просрочено на ${-n} дн.`;
    if (n === 0) return 'сегодня';
    if (n === 1) return 'завтра';
    return `через ${n} дн.`;
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
    if (n < 1024) return `${n} Б`;
    if (n < 1048576) return `${(n / 1024).toFixed(0)} КБ`;
    return `${(n / 1048576).toFixed(1)} МБ`;
}

/** Value for <input type="datetime-local"> from an ISO string. */
export function toLocalInput(value) {
    if (!value) return '';
    const d = new Date(value);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export const STATUS_LABELS = { todo: 'Нужно сделать', in_progress: 'В работе', done: 'Готово' };
export const PRIORITY_LABELS = { low: 'Не срочно', normal: 'Обычная', high: 'Срочно' };
export const MAINTENANCE_KINDS = { consumable: 'Расходники', bill: 'Счёт / оплата', check: 'Проверка', other: 'Другое' };
export const DOC_TYPES = { flight: 'Авиабилет', bus: 'Автобус', train: 'Поезд', hotel: 'Жильё', other: 'Другое' };
export const DOC_ICONS = { flight: 'plane', bus: 'bus', train: 'train-front', hotel: 'bed', other: 'file-text' };
