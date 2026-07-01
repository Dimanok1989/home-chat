<script setup>
import ChatSidebarMenuRow from '../../sidebar/menu/ChatSidebarMenuRow.vue';

defineEmits(['click']);
</script>

<template>
    <ChatSidebarMenuRow @click="$emit('click')">
        <template #icon>
            <span class="flex h-6 w-6 shrink-0 items-center justify-center text-gray-600 dark:text-gray-300">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4"
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
            </span>
        </template>
        Копировать изображение
    </ChatSidebarMenuRow>
</template>
