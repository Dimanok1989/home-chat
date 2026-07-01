<script setup>
import { formatDate } from '../../../utils/chatFormat';

defineProps({
    date: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="flex justify-center py-1">
        <span
            class="rounded-full bg-gray-500/15 px-3 py-1 text-xs font-bold text-gray-600 dark:bg-gray-400/15 dark:text-gray-300"
        >
            {{ formatDate(date) }}
        </span>
    </div>
</template>
