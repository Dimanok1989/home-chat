import { ref } from 'vue';

const message = ref('');
const visible = ref(false);
const type = ref('error'); // 'error' | 'info' | 'success'

let hideTimer = null;

export function useToast() {
    function show(text, toastType = 'error') {
        const trimmed = String(text ?? '').trim();

        if (!trimmed) {
            return;
        }

        message.value = trimmed;
        type.value = toastType;
        visible.value = true;
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            visible.value = false;
        }, 5000);
    }

    function showError(text) {
        show(text, 'error');
    }

    function showInfo(text) {
        show(text, 'info');
    }

    function showSuccess(text) {
        show(text, 'success');
    }

    function hide() {
        visible.value = false;
        clearTimeout(hideTimer);
    }

    return {
        message,
        visible,
        type,
        showError,
        showInfo,
        showSuccess,
        hide,
    };
}
