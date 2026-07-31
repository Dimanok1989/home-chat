<script setup>
import ChatSidebarMenuRow from './ChatSidebarMenuRow.vue';

defineEmits(['click']);
</script>

<template>
    <ChatSidebarMenuRow
        danger
        aria-label="Выйти"
        @click="$emit('click')"
    >
        <template #icon>
            <span class="flex h-6 w-6 shrink-0 items-center justify-center">
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
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" x2="9" y1="12" y2="12" />
                </svg>
            </span>
        </template>
        Выйти
    </ChatSidebarMenuRow>
</template>