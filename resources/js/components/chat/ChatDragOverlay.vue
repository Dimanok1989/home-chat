<script setup>
defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        v-if="visible"
        class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-blue-500/10 backdrop-blur-[1px]"
    >
        <p class="rounded-xl bg-white px-6 py-3 text-sm font-medium text-blue-700 shadow-lg">
            Отпустите изображение для отправки
        </p>
    </div>
</template>
