<script setup>
import ChatSidebarMenuRow from './ChatSidebarMenuRow.vue';
import ChatUserAvatar from '../../shared/ChatUserAvatar.vue';

defineProps({
    avatarUrl: {
        type: String,
        default: null,
    },
    name: {
        type: String,
        default: '',
    },
    initial: {
        type: String,
        default: null,
    },
});

defineEmits(['click']);
</script>

<template>
    <ChatSidebarMenuRow @click="$emit('click')">
        <template #icon>
            <ChatUserAvatar
                :avatar-url="avatarUrl"
                :name="name"
                :initial="initial"
                size="xs"
            />
        </template>
        Профиль
    </ChatSidebarMenuRow>
</template>
