<script setup>
import { nextTick, ref, watch } from 'vue';
import ChatImageContextMenuCopyItem from './menu/ChatImageContextMenuCopyItem.vue';
import ChatImageContextMenuDeleteItem from './menu/ChatImageContextMenuDeleteItem.vue';
import ChatImageContextMenuReplyItem from './menu/ChatImageContextMenuReplyItem.vue';

const props = defineProps({
    contextMenu: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['copy', 'delete', 'reply']);

const menuRef = ref(null);
const menuStyle = ref({ left: '0px', top: '0px', visibility: 'hidden' });

watch(() => props.contextMenu, async (menu) => {
    if (!menu) {
        return;
    }

    menuStyle.value = {
        left: `${menu.x}px`,
        top: `${menu.y}px`,
        visibility: 'hidden',
    };

    await nextTick();

    const el = menuRef.value;

    if (!el) {
        return;
    }

    const padding = 8;
    const rect = el.getBoundingClientRect();
    let left = menu.x;
    let top = menu.y;

    if (left + rect.width > window.innerWidth - padding) {
        left = menu.x - rect.width;
    }

    if (top + rect.height > window.innerHeight - padding) {
        top = window.innerHeight - rect.height - padding;
    }

    left = Math.max(padding, Math.min(left, window.innerWidth - rect.width - padding));
    top = Math.max(padding, Math.min(top, window.innerHeight - rect.height - padding));

    menuStyle.value = {
        left: `${left}px`,
        top: `${top}px`,
        visibility: 'visible',
    };
});
</script>

<template>
    <div
        v-if="contextMenu"
        ref="menuRef"
        class="fixed z-[60] w-48 py-1 rounded-2xl border border-gray-100 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900"
        :style="menuStyle"
    >
        <ChatImageContextMenuReplyItem
            v-if="contextMenu.canReply"
            @click="emit('reply', contextMenu.message)"
        />

        <hr
            v-if="contextMenu.canReply && (contextMenu.imageUrl || contextMenu.canDelete)"
            class="my-1 border-t border-gray-100 dark:border-gray-800"
        />

        <ChatImageContextMenuCopyItem
            v-if="contextMenu.imageUrl"
            @click="emit('copy', contextMenu.imageUrl)"
        />

        <hr
            v-if="contextMenu.imageUrl && contextMenu.canDelete"
            class="my-1 border-t border-gray-100 dark:border-gray-800"
        />

        <ChatImageContextMenuDeleteItem
            v-if="contextMenu.canDelete"
            @click="emit('delete', contextMenu.messageId)"
        />
    </div>
</template>
