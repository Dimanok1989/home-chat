<script setup>
defineProps({
    danger: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['click']);
</script>

<template>
    <div class="px-1">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-2xl px-3 py-2 text-sm font-bold"
            :class="danger
                ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40'
                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700'"
            @click="$emit('click')"
        >
            <slot name="icon" />
            <slot />
        </button>
    </div>
</template>
