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
    const messageDay = calendarDayKey(isoString);
    const todayDay = localDayKey(now);
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayDay = localDayKey(yesterday);

    if (messageDay === todayDay) {
        return 'Сегодня';
    }

    if (messageDay === yesterdayDay) {
        return 'Вчера';
    }

    const options = date.getFullYear() === now.getFullYear()
        ? { day: 'numeric', month: 'long' }
        : { day: 'numeric', month: 'long', year: 'numeric' };

    return date.toLocaleDateString([], options);
}

function localDayKey(date) {
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function calendarDayKey(isoString) {
    if (!isoString) {
        return '';
    }

    return localDayKey(new Date(isoString));
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

export function buildMessagePreview(message) {
    const hasAttachments = (message?.attachments ?? []).length > 0;
    const body = message?.body;

    if ((!body || body === '') && hasAttachments) {
        return 'Изображение';
    }

    if (!body) {
        return '';
    }

    return body.length > 80 ? `${body.slice(0, 80)}…` : body;
}
