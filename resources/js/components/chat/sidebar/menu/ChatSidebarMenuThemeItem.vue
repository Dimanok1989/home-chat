<script setup>
import ChatSidebarMenuRow from './ChatSidebarMenuRow.vue';

defineProps({
    isDark: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['click']);
</script>

<template>
    <ChatSidebarMenuRow
        :aria-label="isDark ? 'Включить светлую тему' : 'Включить тёмную тему'"
        @click="$emit('click')"
    >
        <template #icon>
            <span class="flex h-6 w-6 shrink-0 items-center justify-center text-gray-600 dark:text-gray-300">
                <svg
                    v-if="isDark"
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </span>
        </template>
        {{ isDark ? 'Светлая тема' : 'Тёмная тема' }}
    </ChatSidebarMenuRow>
</template>
