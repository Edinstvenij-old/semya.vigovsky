/**
 * Tiny event bus for "something changed in the family" signals.
 * Pages subscribe with onLive('shopping', reload); the realtime connection
 * (see realtime.js) emits into it. Without realtime, pages fall back to polling.
 */
const handlers = new Map();

export function onLive(type, fn) {
    if (!handlers.has(type)) handlers.set(type, new Set());
    handlers.get(type).add(fn);
    return () => handlers.get(type)?.delete(fn);
}

export function emitLive(type, payload) {
    handlers.get(type)?.forEach((fn) => {
        try {
            fn(payload);
        } catch {
            /* a broken listener must not stop the others */
        }
    });
}
