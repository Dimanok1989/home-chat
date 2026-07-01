<script setup>
import ChatSidebarMenuRow from '../../sidebar/menu/ChatSidebarMenuRow.vue';

defineEmits(['click']);
</script>

<template>
    <ChatSidebarMenuRow danger @click="$emit('click')">
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
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                    <line x1="10" y1="11" x2="10" y2="17" />
                    <line x1="14" y1="11" x2="14" y2="17" />
                </svg>
            </span>
        </template>
        Удалить
    </ChatSidebarMenuRow>
</template>
