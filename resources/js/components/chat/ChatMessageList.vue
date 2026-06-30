<script setup>
import { ref } from 'vue';
import ChatDateSeparator from './ChatDateSeparator.vue';
import ChatMessage from './ChatMessage.vue';
import { shouldShowDateSeparator } from '../../utils/chatFormat';

const props = defineProps({
    messages: {
        type: Array,
        default: () => [],
    },
    loadingOlder: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['scroll', 'openViewer', 'showContextMenu']);

const messagesContainer = ref(null);
const messagesEndRef = ref(null);

defineExpose({
    messagesContainer,
    messagesEndRef,
});
</script>

<template>
    <div
        ref="messagesContainer"
        class="messages-scroll flex-1 space-y-3 overflow-y-auto px-6 py-4"
        @scroll="emit('scroll')"
    >
        <div
            v-if="loadingOlder"
            class="py-2 text-center text-xs text-gray-400 dark:text-gray-500"
        >
            Загрузка...
        </div>

        <template
            v-for="(message, index) in props.messages"
            :key="message.id"
        >
            <ChatDateSeparator
                v-if="shouldShowDateSeparator(props.messages, index)"
                :date="message.created_at"
            />

            <ChatMessage
                :message="message"
                @open-viewer="emit('openViewer', $event)"
                @show-context-menu="(event, payload) => emit('showContextMenu', event, payload)"
            />
        </template>

        <p v-if="props.messages.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400">
            Сообщений пока нет. Напишите первым!
        </p>

        <div ref="messagesEndRef" aria-hidden="true" class="h-px shrink-0" />
    </div>
</template>

<style scoped>
.messages-scroll {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.messages-scroll::-webkit-scrollbar {
    display: none;
}
</style>
