<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg'].includes(value),
    },
});

const sizeClasses = {
    sm: 'h-4 w-4 border-2',
    md: 'h-8 w-8 border-2',
    lg: 'h-12 w-12 border-[3px]',
};
</script>

<template>
    <div
        class="animate-spin rounded-full border-emerald-500 border-t-transparent dark:border-emerald-400"
        :class="sizeClasses[size]"
        role="status"
        aria-label="Загрузка"
    />
</template>
