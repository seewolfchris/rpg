import { readLocalStorageValue, writeLocalStorageValue } from '../immersion/utils';

export const DEFAULT_WORLD_SLUG = 'default';
const WORLD_STORAGE_KEY = 'c76:last-world-slug';

export function persistActiveWorldSlugContext() {
    const worldSlug = resolveActiveWorldSlug();

    if (worldSlug === '') {
        return;
    }

    writeLocalStorageValue(WORLD_STORAGE_KEY, worldSlug);
}

export function resolveStoredWorldSlugContext() {
    const storedValue = readLocalStorageValue(WORLD_STORAGE_KEY);
    const normalized = normalizeWorldSlug(storedValue);

    return normalized ?? '';
}

export function resolveActiveWorldSlug() {
    const htmlSlug = document.documentElement?.dataset?.worldSlug || '';
    const bodySlug = document.body?.dataset?.worldSlug || '';
    const pathMatch = window.location.pathname.match(/^\/w\/([^/]+)/);
    let fromPath = '';

    if (pathMatch && pathMatch[1]) {
        try {
            fromPath = decodeURIComponent(pathMatch[1]);
        } catch {
            fromPath = pathMatch[1];
        }
    }

    return normalizeWorldSlug(htmlSlug || bodySlug || fromPath || '') ?? '';
}

export function normalizeWorldSlug(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const slug = value.trim().toLowerCase();

    if (slug === '' || /^[a-z0-9-]+$/.test(slug) !== true) {
        return null;
    }

    return slug;
}
