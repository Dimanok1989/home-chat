<script setup>
import ChatReplyQuoteBlock from './ChatReplyQuoteBlock.vue';

defineProps({
    replyTo: {
        type: Object,
        required: true,
    },
    isMine: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['scrollToMessage']);
</script>

<template>
    <ChatReplyQuoteBlock
        :title="replyTo.deleted ? 'Сообщение удалено' : replyTo.user_name"
        :subtitle="replyTo.deleted ? '' : replyTo.preview"
        :is-mine="isMine"
        :interactive="!replyTo.deleted"
        :class="{ 'opacity-70': replyTo.deleted }"
        @click="emit('scrollToMessage', replyTo.id)"
    />
</template>
