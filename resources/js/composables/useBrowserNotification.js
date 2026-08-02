/**
 * Composable for browser notifications via the Notification API.
 * Handles permission requests and provides a simple show() method.
 * Falls back silently if the API is unavailable or permission is denied.
 */
export function useBrowserNotification() {
    /**
     * Check if the browser supports the Notification API.
     */
    function isSupported() {
        return 'Notification' in window;
    }

    /**
     * Request permission to show notifications.
     * Returns the permission state ('granted' | 'denied' | 'default').
     */
    async function requestPermission() {
        if (!isSupported()) {
            return 'denied';
        }

        if (Notification.permission === 'granted') {
            return 'granted';
        }

        if (Notification.permission === 'denied') {
            return 'denied';
        }

        try {
            const result = await Notification.requestPermission();
            return result;
        } catch {
            return 'denied';
        }
    }

    /**
     * Show a browser notification.
     *
     * @param {string} title - Notification title
     * @param {object} options - Notification options (body, icon, tag, etc.)
     * @returns {Notification|null} The Notification object, or null if not shown
     */
    function show(title, options = {}) {
        if (!isSupported()) {
            return null;
        }

        if (Notification.permission !== 'granted') {
            return null;
        }

        try {
            const notification = new Notification(title, {
                icon: '/favicon.png',
                ...options,
            });

            // Auto-close after 8 seconds
            setTimeout(() => {
                notification.close();
            }, 8000);

            return notification;
        } catch {
            return null;
        }
    }

    return {
        isSupported,
        requestPermission,
        show,
    };
}