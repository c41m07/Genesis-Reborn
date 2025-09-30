export const SECOND = 1000;
export const MINUTE = 60;
export const HOUR = 3600;
export const DAY = 86400;

export const now = () => Date.now();

export const nowInSeconds = () => Math.floor(now() / SECOND);

export const toSeconds = (value, fallback = 0) => {
    if (value instanceof Date) {
        return Math.floor(value.getTime() / SECOND);
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? Math.floor(value) : fallback;
    }

    if (typeof value === 'string' && value !== '') {
        const numeric = Number(value);
        if (Number.isFinite(numeric)) {
            return Math.floor(numeric);
        }
    }

    return fallback;
};

export const clampSeconds = (value) => Math.max(0, Math.floor(value ?? 0));

export const differenceInSeconds = (target, reference = nowInSeconds()) => {
    const targetSeconds = toSeconds(target, reference);

    return clampSeconds(targetSeconds - reference);
};

export const formatCountdown = (seconds) => {
    const total = clampSeconds(seconds);
    const hrs = Math.floor(total / HOUR);
    const mins = Math.floor((total % HOUR) / MINUTE);
    const secs = total % MINUTE;

    const pad = (value) => String(value).padStart(2, '0');
    if (hrs > 0) {
        return `${hrs}:${pad(mins)}:${pad(secs)}`;
    }

    return `${pad(mins)}:${pad(secs)}`;
};

export const formatDuration = (seconds) => {
    const total = clampSeconds(seconds);
    const hrs = Math.floor(total / HOUR);
    const mins = Math.floor((total % HOUR) / MINUTE);
    const secs = total % MINUTE;

    const parts = [];
    if (hrs > 0) {
        parts.push(`${hrs} h`);
    }
    if (mins > 0) {
        parts.push(`${mins} min`);
    }
    if (parts.length === 0 || secs > 0) {
        parts.push(`${secs} s`);
    }

    return parts.join(' ');
};

export const createServerClock = (serverNowSeconds, reference = nowInSeconds()) => {
    const baseline = toSeconds(serverNowSeconds, reference);
    const offset = baseline - reference;

    const current = () => nowInSeconds() + offset;

    return {
        now: current,
        offset,
    };
};

export default {
    SECOND,
    MINUTE,
    HOUR,
    DAY,
    now,
    nowInSeconds,
    toSeconds,
    clampSeconds,
    differenceInSeconds,
    formatCountdown,
    formatDuration,
    createServerClock,
};
