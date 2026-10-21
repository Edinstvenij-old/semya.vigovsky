import uk from './lang/uk.js';

/**
 * Interface language. Keys are the original Russian strings, so a missing
 * translation simply shows Russian. Placeholders look like ":name".
 * Switching language reloads the page, so module-level labels pick it up too.
 */
export const LOCALES = { ru: 'Русский', uk: 'Українська' };
const dictionaries = { uk };

function detect() {
    try {
        const saved = localStorage.getItem('fh-locale');
        if (saved && saved in LOCALES) return saved;
    } catch {
        /* storage unavailable */
    }
    return (navigator.language || '').toLowerCase().startsWith('uk') ? 'uk' : 'ru';
}

let locale = detect();
document.documentElement.lang = locale;

export function getLocale() {
    return locale;
}

/** Locale for Intl date/number formatting. */
export function intlLocale() {
    return locale === 'uk' ? 'uk-UA' : 'ru-RU';
}

export function setLocale(next) {
    if (!(next in LOCALES)) return;
    try {
        localStorage.setItem('fh-locale', next);
    } catch {
        /* storage unavailable */
    }
    if (next !== locale) window.location.reload();
}

export function t(key, params) {
    let text = dictionaries[locale]?.[key] ?? key;
    if (params) {
        text = text.replace(/:([a-zA-Z]\w*)/g, (match, name) => (name in params ? String(params[name] ?? '') : match));
    }
    return text;
}
