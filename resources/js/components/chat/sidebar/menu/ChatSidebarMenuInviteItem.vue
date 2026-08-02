<script setup>
import ChatSidebarMenuRow from './ChatSidebarMenuRow.vue';

defineEmits(['click']);
</script>

<template>
    <ChatSidebarMenuRow @click="$emit('click')">
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
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="8.5" cy="7" r="4" />
                    <line x1="20" x2="20" y1="8" y2="14" />
                    <line x1="23" x2="17" y1="11" y2="11" />
                </svg>
            </span>
        </template>
        Пригласить пользователя
    </ChatSidebarMenuRow>
</template>