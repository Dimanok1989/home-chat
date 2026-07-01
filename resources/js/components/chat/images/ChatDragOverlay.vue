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
        class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center bg-gradient-to-br from-emerald-200/40 via-teal-100/30 to-blue-200/50 backdrop-blur-[1px] dark:from-emerald-900/30 dark:via-slate-900/40 dark:to-blue-900/30"
    >
        <p class="rounded-xl bg-white/90 px-6 py-3 text-sm font-medium text-teal-800 shadow-sm dark:bg-gray-800/90 dark:text-teal-300">
            Отпустите изображение для отправки
        </p>
    </div>
</template>
