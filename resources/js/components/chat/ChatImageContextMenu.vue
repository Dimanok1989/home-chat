<script setup>
defineProps({
    contextMenu: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['copy']);
</script>

<template>
    <div
        v-if="contextMenu"
        class="fixed z-[60] min-w-[180px] rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
        :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
    >
        <button
            type="button"
            class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
            @click="emit('copy', contextMenu.url)"
        >
            Копировать изображение
        </button>
    </div>
</template>
