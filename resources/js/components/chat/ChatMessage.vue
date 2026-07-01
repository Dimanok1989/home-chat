<script setup>
import { formatDateTime, formatTime, isSystemMessage } from '../../utils/chatFormat';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['openViewer', 'showContextMenu']);

function messageCanDelete(msg) {
    return msg.is_mine && !isSystemMessage(msg);
}

function handleImageClick(attachment) {
    emit('openViewer', attachment);
}

function handleImageContextMenu(event, attachment) {
    event.stopPropagation();
    emit('showContextMenu', event, {
        message: props.message,
        imageUrl: attachment.url,
    });
}

function handleMessageContextMenu(event) {
    if (!messageCanDelete(props.message)) {
        return;
    }

    emit('showContextMenu', event, {
        message: props.message,
        imageUrl: null,
    });
}
</script>

<template>
    <div
        class="flex"
        :class="isSystemMessage(message)
            ? 'justify-center'
            : (message.is_mine ? 'justify-end' : 'justify-start')"
    >
        <div
            v-if="isSystemMessage(message)"
            :title="formatDateTime(message.created_at)"
            class="max-w-[85%] cursor-default rounded-3xl bg-gray-600/10 px-5 py-3 text-center text-sm text-gray-700 dark:bg-gray-700/30 dark:text-gray-300"
        >
            <div
                v-if="message.attachments?.length"
                class="flex flex-col items-center gap-2"
                :class="{ 'mb-2': message.body }"
            >
                <img
                    v-for="attachment in message.attachments"
                    :key="attachment.id"
                    :src="attachment.url"
                    :alt="attachment.original_name"
                    class="max-h-64 cursor-pointer rounded-2xl object-contain"
                    @click="handleImageClick(attachment)"
                    @contextmenu.prevent="handleImageContextMenu($event, attachment)"
                />
            </div>

            <p
                v-if="message.body"
                class="whitespace-pre-wrap break-words"
            >
                {{ message.body }}
            </p>
        </div>

        <div
            v-else
            class="max-w-[75%] rounded-2xl px-4 py-2"
            :class="[
                message.is_mine
                    ? 'bg-blue-200 text-gray-900 dark:bg-blue-900 dark:text-gray-100'
                    : 'border border-gray-100 bg-white text-gray-900 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-100',
                messageCanDelete(message) ? 'cursor-context-menu' : '',
            ]"
            @contextmenu.prevent="handleMessageContextMenu"
        >
            <p v-if="!message.is_mine" class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                {{ message.user_name }}
            </p>

            <div
                v-if="message.attachments?.length"
                class="mb-2 space-y-2"
            >
                <img
                    v-for="attachment in message.attachments"
                    :key="attachment.id"
                    :src="attachment.url"
                    :alt="attachment.original_name"
                    class="max-h-64 cursor-pointer rounded-lg object-cover"
                    @click="handleImageClick(attachment)"
                    @contextmenu.prevent="handleImageContextMenu($event, attachment)"
                />
            </div>

            <p
                v-if="message.body"
                class="whitespace-pre-wrap break-words text-sm"
            >
                {{ message.body }}
            </p>

            <p
                class="mt-1 text-right text-xs"
                :class="message.is_mine ? 'text-gray-500 dark:text-gray-400' : 'text-gray-400 dark:text-gray-500'"
            >
                {{ formatTime(message.created_at) }}
            </p>
        </div>
    </div>
</template>
