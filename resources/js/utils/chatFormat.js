export function formatTime(isoString) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDateTime(isoString) {
    if (!isoString) {
        return '';
    }

    return new Date(isoString).toLocaleString([], {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function isSystemMessage(message) {
    if (typeof message.is_system === 'boolean') {
        return message.is_system;
    }

    return !message.user_name;
}
