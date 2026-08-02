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

const CALL_BODY_PREFIX = '\u{1F4DE}';

/**
 * Check if a message is a call history message.
 * Call messages have a body starting with the 📞 prefix.
 */
export function isCallMessage(message) {
    return message?.body?.startsWith(CALL_BODY_PREFIX) ?? false;
}

/**
 * Parse a call message body and return display info.
 *
 * @param {object} message
 * @param {boolean} message.is_mine - Whether the message belongs to the current user
 * @param {string} message.body - The message body (e.g. "📞 02:34" or "📞 Звонок был отклонен")
 * @returns {{ icon: string, label: string, duration: string|null }|null}
 */
export function parseCallMessage(message) {
    if (!isCallMessage(message)) return null;

    const body = message.body.slice(CALL_BODY_PREFIX.length).trim();
    const isOutgoing = message.is_mine;

    // Check if it's a rejected call (body contains "Звонок был отклонен")
    if (body.includes('Звонок был отклонен')) {
        return {
            icon: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z',
            label: 'Звонок был отклонен',
            duration: null,
        };
    }

    // Check if it's a cancelled call (caller hung up before answer)
    if (body.includes('Звонок был отменен')) {
        return {
            icon: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z',
            label: 'Звонок был отменен',
            duration: null,
        };
    }

    // Answered call — body contains the duration (e.g. "02:34")
    return {
        icon: isOutgoing
            ? 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z'
            : 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z',
        label: isOutgoing ? 'Исходящий звонок' : 'Входящий звонок',
        duration: body,
    };
}

/**
 * Parse a registration system message body and extract the user info.
 * Format: "Новый пользователь [[Name|id]] зарегистрировался."
 *
 * @param {string} body
 * @returns {{ name: string, id: number }|null}
 */
export function parseRegistrationMessage(body) {
    if (!body) return null;

    const match = body.match(/Новый пользователь \[\[(.+?)\|(\d+)\]\] зарегистрировался\./);
    if (!match) return null;

    return {
        name: match[1],
        id: Number(match[2]),
    };
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
