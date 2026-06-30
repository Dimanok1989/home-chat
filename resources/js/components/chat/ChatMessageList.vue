<script setup>
import { ref } from 'vue';
import ChatMessage from './ChatMessage.vue';

defineProps({
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
            class="py-2 text-center text-xs text-gray-400"
        >
            Загрузка...
        </div>

        <ChatMessage
            v-for="message in messages"
            :key="message.id"
            :message="message"
            @open-viewer="emit('openViewer', $event)"
            @show-context-menu="(event, url) => emit('showContextMenu', event, url)"
        />

        <p v-if="messages.length === 0" class="text-center text-sm text-gray-500">
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
