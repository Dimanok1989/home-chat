<script setup>
import { formatDateTime, formatTime, isSystemMessage } from '../../utils/chatFormat';

defineProps({
    message: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['openViewer', 'showContextMenu']);

function handleImageClick(attachment) {
    emit('openViewer', attachment);
}

function handleImageContextMenu(event, url) {
    emit('showContextMenu', event, url);
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
            class="max-w-[85%] cursor-default rounded-3xl bg-gray-200 px-5 py-3 text-center text-sm text-gray-600"
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
                    @contextmenu.prevent="handleImageContextMenu($event, attachment.url)"
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
            class="max-w-[75%] rounded-2xl px-4 py-2 shadow-sm"
            :class="message.is_mine
                ? 'bg-blue-600 text-white'
                : 'border border-gray-200 bg-white text-gray-900'"
        >
            <p v-if="!message.is_mine" class="mb-1 text-xs font-medium text-gray-500">
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
                    @contextmenu.prevent="handleImageContextMenu($event, attachment.url)"
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
                :class="message.is_mine ? 'text-blue-100' : 'text-gray-400'"
            >
                {{ formatTime(message.created_at) }}
            </p>
        </div>
    </div>
</template>
