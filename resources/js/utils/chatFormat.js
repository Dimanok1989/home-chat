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

export function formatDate(isoString) {
    if (!isoString) {
        return '';
    }

    const date = new Date(isoString);
    const now = new Date();
    const options = date.getFullYear() === now.getFullYear()
        ? { day: 'numeric', month: 'long' }
        : { day: 'numeric', month: 'long', year: 'numeric' };

    return date.toLocaleDateString([], options);
}

function calendarDayKey(isoString) {
    if (!isoString) {
        return '';
    }

    const date = new Date(isoString);

    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

export function shouldShowDateSeparator(messages, index) {
    const message = messages[index];

    if (!message || isSystemMessage(message)) {
        return false;
    }

    const messageDay = calendarDayKey(message.created_at);

    for (let i = index - 1; i >= 0; i -= 1) {
        if (isSystemMessage(messages[i])) {
            continue;
        }

        return calendarDayKey(messages[i].created_at) !== messageDay;
    }

    return true;
}

export function isSystemMessage(message) {
    if (typeof message.is_system === 'boolean') {
        return message.is_system;
    }

    return !message.user_name;
}
