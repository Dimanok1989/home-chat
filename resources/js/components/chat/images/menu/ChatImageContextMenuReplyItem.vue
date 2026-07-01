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
                    <polyline points="9 17 4 12 9 7" />
                    <path d="M20 18v-2a4 4 0 0 0-4-4H4" />
                </svg>
            </span>
        </template>
        Ответить
    </ChatSidebarMenuRow>
</template>
