import { onMounted, onUnmounted, ref } from 'vue';

export function useMediaQuery(query) {
    const matches = ref(
        typeof window !== 'undefined' ? window.matchMedia(query).matches : false,
    );

    let mediaQueryList = null;

    function update() {
        matches.value = mediaQueryList?.matches ?? false;
    }

    onMounted(() => {
        mediaQueryList = window.matchMedia(query);
        update();
        mediaQueryList.addEventListener('change', update);
    });

    onUnmounted(() => {
        mediaQueryList?.removeEventListener('change', update);
    });

    return matches;
}
