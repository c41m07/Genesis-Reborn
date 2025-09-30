const registry = new Map();

export const on = (eventName, handler) => {
    if (typeof handler !== 'function' || !eventName) {
        return () => {
        };
    }

    const listeners = registry.get(eventName) ?? new Set();
    listeners.add(handler);
    registry.set(eventName, listeners);

    return () => off(eventName, handler);
};

export const off = (eventName, handler) => {
    if (!eventName) {
        return;
    }

    const listeners = registry.get(eventName);
    if (!listeners) {
        return;
    }

    if (handler) {
        listeners.delete(handler);
    }

    if (!handler || listeners.size === 0) {
        registry.delete(eventName);
    }
};

export const emit = (eventName, detail) => {
    if (!eventName) {
        return;
    }

    const listeners = registry.get(eventName);
    if (!listeners) {
        return;
    }

    [...listeners].forEach((listener) => {
        try {
            listener(detail);
        } catch (error) {
            console.error('[events] listener failure', error);
        }
    });
};

export default {
    on,
    off,
    emit,
};
