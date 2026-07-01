<script setup>
import { buildMessagePreview } from '../../../utils/chatFormat';
import ChatReplyQuoteBlock from './ChatReplyQuoteBlock.vue';

defineProps({
    message: {
        type: Object,
        required: true,
    },
});

defineEmits(['cancel']);
</script>

<template>
    <div class="mb-2 flex items-stretch gap-2">
        <ChatReplyQuoteBlock
            class="min-w-0 flex-1"
            compact
            :title="message.user_name"
            :subtitle="buildMessagePreview(message)"
            :is-mine="false"
        />

        <button
            type="button"
            class="flex h-8 w-8 shrink-0 items-center justify-center self-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200 cursor-pointer"
            aria-label="Отменить ответ"
            @click="$emit('cancel')"
        >
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
                <path d="M18 6L6 18M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
