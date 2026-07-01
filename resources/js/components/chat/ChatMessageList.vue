<script setup>
import { computed, ref } from 'vue';
import ChatDateSeparator from './ChatDateSeparator.vue';
import ChatMessage from './ChatMessage.vue';
import ChatSpinner from './ChatSpinner.vue';
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
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['scroll', 'openViewer', 'showContextMenu']);

const messagesContainer = ref(null);

const displayMessages = computed(() => [...props.messages].reverse());

function originalIndex(displayIndex) {
    return props.messages.length - 1 - displayIndex;
}

defineExpose({
    messagesContainer,
});
</script>

<template>
    <div
        ref="messagesContainer"
        class="messages-scroll flex flex-1 flex-col-reverse gap-3 overflow-y-auto px-6 py-4"
        @scroll="emit('scroll')"
    >
        <p
            v-if="props.messages.length === 0 && !props.loading"
            class="text-center text-sm text-gray-500 dark:text-gray-400"
        >
            Сообщений пока нет. Напишите первым!
        </p>

        <template
            v-for="(message, displayIndex) in displayMessages"
            :key="message.id"
        >
            <ChatMessage
                :message="message"
                @open-viewer="emit('openViewer', $event)"
                @show-context-menu="(event, payload) => emit('showContextMenu', event, payload)"
            />

            <ChatDateSeparator
                v-if="shouldShowDateSeparator(props.messages, originalIndex(displayIndex))"
                :date="message.created_at"
            />
        </template>

        <div
            v-if="loadingOlder"
            class="flex justify-center py-2"
        >
            <ChatSpinner size="sm" />
        </div>
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
