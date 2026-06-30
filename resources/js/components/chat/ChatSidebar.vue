<script setup>
import { computed } from 'vue';

const props = defineProps({
    onlineUsers: {
        type: Array,
        default: () => [],
    },
});

const onlineCount = computed(() => props.onlineUsers.length);
</script>

<template>
    <aside class="flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-4 py-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Онлайн</h2>
            <p class="mt-1 text-2xl font-bold text-gray-900">{{ onlineCount }}</p>
        </div>

        <ul class="flex-1 overflow-y-auto p-3">
            <li
                v-for="user in onlineUsers"
                :key="user.id"
                class="mb-2 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm"
            >
                <span class="h-2 w-2 shrink-0 rounded-full bg-green-500"></span>
                <span class="truncate font-medium">{{ user.name }}</span>
            </li>

            <li v-if="onlineUsers.length === 0" class="px-3 py-2 text-sm text-gray-500">
                Пока никого нет
            </li>
        </ul>
    </aside>
</template>
