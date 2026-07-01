import { ref } from 'vue';

const message = ref('');
const visible = ref(false);

let hideTimer = null;

export function useToast() {
    function showError(text) {
        const trimmed = String(text ?? '').trim();

        if (!trimmed) {
            return;
        }

        message.value = trimmed;
        visible.value = true;
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            visible.value = false;
        }, 5000);
    }

    function hide() {
        visible.value = false;
        clearTimeout(hideTimer);
    }

    return {
        message,
        visible,
        showError,
        hide,
    };
}
