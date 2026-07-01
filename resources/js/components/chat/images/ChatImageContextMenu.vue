<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    contextMenu: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['copy', 'delete']);

const menuRef = ref(null);
const menuStyle = ref({ left: '0px', top: '0px', visibility: 'hidden' });

watch(() => props.contextMenu, async (menu) => {
    if (!menu) {
        return;
    }

    menuStyle.value = {
        left: `${menu.x}px`,
        top: `${menu.y}px`,
        visibility: 'hidden',
    };

    await nextTick();

    const el = menuRef.value;

    if (!el) {
        return;
    }

    const padding = 8;
    const rect = el.getBoundingClientRect();
    let left = menu.x;
    let top = menu.y;

    if (left + rect.width > window.innerWidth - padding) {
        left = menu.x - rect.width;
    }

    if (top + rect.height > window.innerHeight - padding) {
        top = window.innerHeight - rect.height - padding;
    }

    left = Math.max(padding, Math.min(left, window.innerWidth - rect.width - padding));
    top = Math.max(padding, Math.min(top, window.innerHeight - rect.height - padding));

    menuStyle.value = {
        left: `${left}px`,
        top: `${top}px`,
        visibility: 'visible',
    };
});
</script>

<template>
    <div
        v-if="contextMenu"
        ref="menuRef"
        class="fixed z-[60] min-w-[200px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800"
        :style="menuStyle"
    >
        <button
            v-if="contextMenu.imageUrl"
            type="button"
            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700"
            @click="emit('copy', contextMenu.imageUrl)"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 shrink-0"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
            </svg>
            Копировать изображение
        </button>

        <button
            v-if="contextMenu.canDelete"
            type="button"
            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
            @click="emit('delete', contextMenu.messageId)"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 shrink-0"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="3 6 5 6 21 6" />
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                <line x1="10" y1="11" x2="10" y2="17" />
                <line x1="14" y1="11" x2="14" y2="17" />
            </svg>
            Удалить
        </button>
    </div>
</template>
